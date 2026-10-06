<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PartnerVerificationStatus;
use App\Enums\PropertyStatus;
use App\Enums\RegistrationFieldScope;
use App\Enums\Status;
use App\Mail\PartnerApprovedMailable;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PartnerCountry;
use App\Models\PartnerRegistrationValue;
use App\Models\Property;
use App\Models\RegistrationField;
use App\Models\Setting;
use App\Support\SystemMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class PartnerVerificationService
{
    /**
     * A partner is complete once they've selected a property type, are
     * attached to at least one country, and have satisfied every required
     * (is_mandatory) partner-scope registration field for that country.
     */
    public function isProfileComplete(Partner $partner): bool
    {
        if ($partner->property_type_id === null) {
            return false;
        }

        $countryIds = $partner->countries()->pluck('countries.id');

        if ($countryIds->isEmpty()) {
            return false;
        }

        $requiredFieldIds = RegistrationField::query()
            ->forScope(RegistrationFieldScope::Partner)
            ->whereIn('country_id', $countryIds)
            ->where('status', Status::Active)
            ->where('is_mandatory', true)
            ->pluck('id');

        if ($requiredFieldIds->isEmpty()) {
            return true;
        }

        $satisfiedFieldIds = PartnerRegistrationValue::query()
            ->where('partner_id', $partner->id)
            ->whereIn('registration_field_id', $requiredFieldIds)
            ->pluck('registration_field_id')
            ->unique();

        return $requiredFieldIds->diff($satisfiedFieldIds)->isEmpty();
    }

    /**
     * Moves a Rejected/CorrectionRequested partner into Resubmission after
     * they've edited any part of their profile (personal info, address, or
     * registration fields), then speculatively runs the auto-approve check.
     * Safe to call after any partner-profile save — a no-op for partners not
     * currently in a "needs fix" state.
     */
    public function resubmitIfNeeded(Partner $partner): void
    {
        if (in_array($partner->verification_status, [
            PartnerVerificationStatus::Rejected,
            PartnerVerificationStatus::CorrectionRequested,
        ], true)) {
            $partner->update(['verification_status' => PartnerVerificationStatus::Resubmission]);
        }

        $this->maybeAutoApprove($partner->fresh());
    }

    /**
     * Approves the partner automatically once the "Auto-approve partners"
     * setting is on and their profile is complete. Safe to call speculatively
     * after any write that might have just completed a partner's profile —
     * it's a no-op unless every condition is met, including for partners
     * resubmitting after a Rejected/CorrectionRequested review.
     *
     * @return bool true if this call actually approved the partner
     */
    public function maybeAutoApprove(Partner $partner): bool
    {
        if (! SystemMode::isMulti() || ! (bool) Setting::get('auto_approve_partners', false)) {
            return false;
        }

        if (in_array($partner->verification_status, [
            PartnerVerificationStatus::Approved,
            PartnerVerificationStatus::Suspended,
        ], true)) {
            return false;
        }

        if (! $this->isProfileComplete($partner)) {
            return false;
        }

        $partner->update([
            'verification_status' => PartnerVerificationStatus::Approved,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        activity()
            ->performedOn($partner)
            ->causedBy(auth()->user())
            ->event('auto_approved')
            ->log('Partner account was automatically approved (auto-approve enabled)');

        Mail::to($partner->user->email)->queue(new PartnerApprovedMailable($partner));

        return true;
    }

    /**
     * Sweeps every partner not yet approved/suspended and auto-approves any
     * that are already complete. Meant to run once, right when the
     * "Auto-approve partners" setting is switched from off to on — without
     * this, a partner who finished their profile before the toggle existed
     * would sit in the queue forever, since nothing else would ever
     * re-trigger their completeness check.
     *
     * @return int number of partners approved by this sweep
     */
    public function autoApproveAllEligible(): int
    {
        $approvedCount = 0;

        Partner::query()
            ->whereNotIn('verification_status', [
                PartnerVerificationStatus::Approved,
                PartnerVerificationStatus::Suspended,
            ])
            ->with('countries')
            ->chunkById(100, function ($partners) use (&$approvedCount): void {
                foreach ($partners as $partner) {
                    if ($this->maybeAutoApprove($partner)) {
                        $approvedCount++;
                    }
                }
            });

        return $approvedCount;
    }

    /**
     * Suspends a partner and cascades the suspension to every currently-active
     * property, so a suspended partner's properties disappear from the
     * customer app/API immediately. Only properties that are live right now
     * are touched — anything already Inactive, or already suspended
     * independently by an admin, is left untouched. The touched property IDs
     * are recorded on the partner so {@see unsuspendPartner()} restores
     * exactly those and nothing else.
     *
     * @throws \InvalidArgumentException when any of the partner's properties
     *                                   has an active or upcoming Confirmed/CheckedIn/PendingPayment booking.
     */
    public function suspendPartner(Partner $partner, string $reason): void
    {
        $hasActiveBookings = $partner->properties()
            ->whereHas('bookings', function (Builder $query): void {
                $query->whereIn('status', [
                    BookingStatus::Confirmed->value,
                    BookingStatus::CheckedIn->value,
                    BookingStatus::PendingPayment->value,
                ]);
            })
            ->exists();

        if ($hasActiveBookings) {
            throw new \InvalidArgumentException(__('admin.cannot_suspend_active_bookings'));
        }

        $propertyService = app(PropertyService::class);
        $cascadedIds = [];

        $partner->properties()
            ->where('status', PropertyStatus::Active)
            ->each(function (Property $property) use ($propertyService, &$cascadedIds): void {
                try {
                    $propertyService->suspendProperty($property, __('admin.property_suspended_partner_cascade_reason'));
                    $cascadedIds[] = $property->id;
                } catch (\InvalidArgumentException) {
                    // Active/upcoming bookings on this property — the partner-level
                    // check above already guards against this in the normal case,
                    // so this is defensive only.
                }
            });

        $partner->update([
            'verification_status' => PartnerVerificationStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
            'cascade_suspended_property_ids' => $cascadedIds,
        ]);

        activity()
            ->performedOn($partner)
            ->causedBy(auth()->user())
            ->event('suspended')
            ->log('Partner account was suspended');
    }

    /**
     * Restores exactly the properties {@see suspendPartner()} cascaded to,
     * then reinstates the partner. Properties suspended independently of
     * that cascade are left untouched.
     */
    public function unsuspendPartner(Partner $partner): void
    {
        $propertyService = app(PropertyService::class);

        Property::query()
            ->whereIn('id', $partner->cascade_suspended_property_ids ?? [])
            ->where('status', PropertyStatus::Suspended)
            ->each(fn (Property $property) => $propertyService->unsuspendProperty($property));

        $partner->update([
            'verification_status' => PartnerVerificationStatus::Approved,
            'suspended_at' => null,
            'suspension_reason' => null,
            'cascade_suspended_property_ids' => null,
        ]);

        activity()
            ->performedOn($partner)
            ->causedBy(auth()->user())
            ->event('unsuspended')
            ->log('Partner account was unsuspended');
    }

    /**
     * Deactivates a partner's operation in one country: blocks if any
     * property there has an active or upcoming booking, otherwise cascades a
     * suspension to every currently-active property in that country (which
     * also hides them from Property::scopePubliclyVisible()) and records
     * which ones so {@see activatePartnerCountry()} restores exactly those.
     * Mirrors {@see suspendPartner()}, scoped to a single country instead of
     * the whole partner.
     *
     * @throws \InvalidArgumentException when any property in the country has an active or upcoming Confirmed/CheckedIn/PendingPayment booking.
     */
    public function deactivatePartnerCountry(Partner $partner, Country $country): void
    {
        $hasActiveBookings = $partner->properties()
            ->where('country_id', $country->id)
            ->whereHas('bookings', function (Builder $query): void {
                $query->whereIn('status', [
                    BookingStatus::Confirmed->value,
                    BookingStatus::CheckedIn->value,
                    BookingStatus::PendingPayment->value,
                ]);
            })
            ->exists();

        if ($hasActiveBookings) {
            throw new \InvalidArgumentException(__('admin.cannot_deactivate_country_active_bookings'));
        }

        $propertyService = app(PropertyService::class);
        $cascadedIds = [];

        $partner->properties()
            ->where('country_id', $country->id)
            ->where('status', PropertyStatus::Active)
            ->each(function (Property $property) use ($propertyService, &$cascadedIds): void {
                try {
                    $propertyService->suspendProperty($property, __('admin.property_suspended_country_deactivated_reason'));
                    $cascadedIds[] = $property->id;
                } catch (\InvalidArgumentException) {
                    // Active/upcoming bookings on this property — the country-level
                    // check above already guards against this in the normal case,
                    // so this is defensive only.
                }
            });

        $partner->countries()->updateExistingPivot($country->id, [
            'is_active' => false,
            'cascade_suspended_property_ids' => $cascadedIds,
        ]);

        activity()
            ->performedOn($partner)
            ->causedBy(auth()->user())
            ->event('country_deactivated')
            ->withProperties(['country' => $country->name])
            ->log("Partner deactivated operations in {$country->name}");
    }

    /**
     * Restores exactly the properties {@see deactivatePartnerCountry()}
     * cascaded to, then reinstates the country. Properties suspended
     * independently of that cascade are left untouched.
     */
    public function activatePartnerCountry(Partner $partner, Country $country): void
    {
        /** @var PartnerCountry|null $pivot */
        $pivot = $partner->countries()->where('countries.id', $country->id)->first()?->pivot;

        $propertyService = app(PropertyService::class);

        Property::query()
            ->whereIn('id', $pivot?->cascade_suspended_property_ids ?? [])
            ->where('status', PropertyStatus::Suspended)
            ->each(fn (Property $property) => $propertyService->unsuspendProperty($property));

        $partner->countries()->updateExistingPivot($country->id, [
            'is_active' => true,
            'cascade_suspended_property_ids' => null,
        ]);

        activity()
            ->performedOn($partner)
            ->causedBy(auth()->user())
            ->event('country_activated')
            ->withProperties(['country' => $country->name])
            ->log("Partner reactivated operations in {$country->name}");
    }
}
