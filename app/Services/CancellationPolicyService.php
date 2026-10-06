<?php

namespace App\Services;

use App\Enums\PropertyCancellationPolicySource;
use App\Enums\SetupTask;
use App\Mail\CancellationPolicyUpdatedMailable;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\Country;
use App\Models\CountrySetupTask;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Scopes\PartnerScope;
use App\Support\PercentageValidator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class CancellationPolicyService
{
    public function getActivePolicy(User $user): CancellationPolicy
    {
        $countryId = $user->current_country_id ?? Country::query()->first()?->id ?? 1;
        $propertyTypeId = PropertyType::where('is_active', true)->orderBy('id')->first()?->id ?? 1;

        return $this->getAdminDefaultPolicy($countryId, $propertyTypeId);
    }

    /**
     * The admin's Country + Property Type default policy — the fallback every
     * partner property uses unless it has its own custom policy selected.
     */
    public function getAdminDefaultPolicy(int $countryId, int $propertyTypeId): CancellationPolicy
    {
        return $this->findOrCreateUnique([
            'country_id' => $countryId,
            'partner_id' => null,
            'property_type_id' => $propertyTypeId,
        ]);
    }

    public function getActivePolicyForPartner(Partner $partner, int $countryId): CancellationPolicy
    {
        return $this->findOrCreateUnique([
            'country_id' => $countryId,
            'partner_id' => $partner->id,
            'property_type_id' => null,
        ]);
    }

    /**
     * CancellationPolicy has a global PartnerScope that silently adds
     * `partner_id = <the authenticated partner's id>` to every query while a
     * partner is logged in. Explicitly bypassed here — this method is always
     * called with the exact partner_id (or null, for an admin-default lookup)
     * already in $criteria, so an implicit extra filter is never wanted: for
     * an admin-default lookup it makes `partner_id IS NULL AND partner_id = X`
     * impossible to ever match, so firstOrCreate() previously created a brand
     * new duplicate policy on every single partner request.
     *
     * Also self-heals: firstOrCreate() isn't atomic (a SELECT, then only if
     * empty an INSERT), so two near-simultaneous requests could both see
     * "nothing found" and both insert — there's no DB-level unique constraint
     * on this table to catch it (partner_id being nullable makes a naive
     * unique index ineffective anyway, since MySQL treats every NULL as
     * distinct). Collapsing any duplicates this exact criteria turns up down
     * to the oldest one means leftover races never accumulate indefinitely.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function findOrCreateUnique(array $criteria): CancellationPolicy
    {
        $matches = CancellationPolicy::withoutGlobalScope(PartnerScope::class)
            ->where($criteria)
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return CancellationPolicy::query()->create($criteria + [
                'cancellation_cutoff_time' => '14:00:00',
                'is_active' => true,
            ]);
        }

        $keeper = $matches->shift();

        if ($matches->isNotEmpty()) {
            CancellationPolicyRule::query()->whereIn('cancellation_policy_id', $matches->pluck('id'))->delete();
            CancellationPolicy::withoutGlobalScope(PartnerScope::class)->whereIn('id', $matches->pluck('id'))->delete();
        }

        return $keeper;
    }

    /**
     * Bypasses PartnerScope throughout — this looks up a policy by the property's
     * own partner_id/country_id/property_type_id explicitly, so an implicit
     * "restrict to the authenticated partner" filter must never apply here. Without
     * this, a partner-triggered cancellation (e.g. a partner cancelling a guest's
     * booking) would silently fail to find the admin-default fallback policy —
     * `partner_id IS NULL AND partner_id = <that partner>` can never match — and
     * the refund calculation would fall through with no policy at all.
     */
    public function getPolicyForProperty(Property $property): ?CancellationPolicy
    {
        $propertyTypeId = $property->resolvedPropertyTypeId();

        // Partner-specific policy takes priority over admin global
        if ($property->partner_id) {
            $partnerPolicy = CancellationPolicy::withoutGlobalScope(PartnerScope::class)
                ->with('rules')
                ->where('country_id', $property->country_id)
                ->where('partner_id', $property->partner_id)
                ->where('is_active', true)
                ->first();

            if ($partnerPolicy) {
                return $partnerPolicy;
            }
        }

        // Fall back to admin global policy
        return CancellationPolicy::withoutGlobalScope(PartnerScope::class)
            ->with('rules')
            ->where('country_id', $property->country_id)
            ->whereNull('partner_id')
            ->where('property_type_id', $propertyTypeId)
            ->where('is_active', true)
            ->first();
    }

    public function saveCutoffTime(CancellationPolicy $policy, string $cutoffTime): void
    {
        $policy->update(['cancellation_cutoff_time' => $cutoffTime]);

        $this->checkAndMarkComplete($policy);
        $this->notifyAffectedPartners($policy);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRules(CancellationPolicy $policy): array
    {
        return $policy->rules()->orderByDesc('days_before_checkin')->get()->toArray();
    }

    /**
     * @param  array{days_before_checkin: int, refund_percentage: int}  $data
     */
    public function saveRule(CancellationPolicy $policy, array $data, ?int $editingRuleId = null): bool
    {
        PercentageValidator::assertValid((float) $data['refund_percentage'], 'Refund percentage');

        $existing = CancellationPolicyRule::query()
            ->where('cancellation_policy_id', $policy->id)
            ->where('days_before_checkin', $data['days_before_checkin'])
            ->where('id', '!=', $editingRuleId)
            ->exists();

        if ($existing) {
            return false;
        }

        if ($editingRuleId) {
            CancellationPolicyRule::query()->where('id', $editingRuleId)->update($data);
        } else {
            CancellationPolicyRule::query()->create([
                'cancellation_policy_id' => $policy->id,
                ...$data,
            ]);
        }

        $this->checkAndMarkComplete($policy);
        $this->notifyAffectedPartners($policy);

        return true;
    }

    /**
     * Returns false if the rule is the mandatory 0-day fallback or not found.
     */
    public function deleteRule(int $ruleId): bool
    {
        $rule = CancellationPolicyRule::query()->find($ruleId);

        if (! $rule) {
            return false;
        }

        if ($rule->days_before_checkin === 0) {
            return false;
        }

        $policy = CancellationPolicy::find($rule->cancellation_policy_id);

        $rule->delete();

        if ($policy) {
            $this->notifyAffectedPartners($policy);
        }

        return true;
    }

    public function checkAndMarkComplete(CancellationPolicy $policy): void
    {
        // Partner policies don't affect admin country setup tasks
        if ($policy->partner_id !== null) {
            return;
        }

        if (empty($policy->cancellation_cutoff_time)) {
            return;
        }

        $hasRule = $policy->rules()->exists();

        if ($hasRule) {
            CountrySetupTask::markComplete(SetupTask::CancellationPolicy, $policy->country_id);
        }
    }

    /**
     * Calculate refund percentage based on cancellation policy and property timezone.
     * Uses the booking's stored snapshot when available so rule changes after booking don't affect the refund.
     */
    public function calculateRefundPercentage(Booking $booking): int
    {
        $booking->loadMissing('property.country');

        $property = $booking->property;

        if (! $property) {
            return 0;
        }

        $propertyTimezone = $property->timezone ?? $property->country?->timezone ?? 'UTC';

        $nowInPropertyTimezone = now()->setTimezone($propertyTimezone);
        $effectiveDate = $nowInPropertyTimezone->copy();

        $snapshot = $booking->cancellation_policy_snapshot;

        if ($snapshot && ! empty($snapshot['cancellation_cutoff_time'])) {
            $cutoffTime = $effectiveDate->copy()->setTimeFromTimeString($snapshot['cancellation_cutoff_time']);
            if ($nowInPropertyTimezone->gt($cutoffTime)) {
                $effectiveDate->addDay();
            }

            $checkInDate = $booking->check_in->copy()->startOfDay();
            $daysUntilCheckin = $effectiveDate->copy()->startOfDay()->diffInDays($checkInDate, false);

            if ($daysUntilCheckin < 0) {
                return 0;
            }

            $rule = collect($snapshot['rules'])
                ->filter(fn ($r) => (int) $r['days_before_checkin'] <= $daysUntilCheckin)
                ->sortByDesc('days_before_checkin')
                ->first();

            return (int) ($rule['refund_percentage'] ?? 0);
        }

        // Fallback for bookings created before snapshot was introduced
        $policy = $this->resolvePolicyForRefund($property);

        if (! $policy) {
            return 0;
        }

        $cutoffTime = $effectiveDate->copy()->setTimeFromTimeString($policy->cancellation_cutoff_time);
        if ($nowInPropertyTimezone->gt($cutoffTime)) {
            $effectiveDate->addDay();
        }

        $checkInDate = $booking->check_in->copy()->startOfDay();
        $daysUntilCheckin = $effectiveDate->copy()->startOfDay()->diffInDays($checkInDate, false);

        if ($daysUntilCheckin < 0) {
            return 0;
        }

        $rule = $policy->rules()
            ->where('days_before_checkin', '<=', $daysUntilCheckin)
            ->orderByDesc('days_before_checkin')
            ->first();

        return $rule->refund_percentage ?? 0;
    }

    /**
     * Full cancellation financial breakdown for a booking at a given refund
     * percentage — the single source for both the customer's refund amount and
     * the partner's wallet credit, so the two can never go out of sync.
     *
     * Retained Room is calculated from the original, undiscounted base_amount —
     * a promo discount is absorbed entirely by the platform, it never reduces
     * what's retained (matching CommissionService::calculatePartnerCredit(),
     * which also always uses base_amount). The tax rate is derived from the
     * booking's own snapshotted tax_amount rather than re-querying the taxes
     * table, so this stays correct even if tax config changes after booking.
     *
     * The theoretical retained room + tax is capped at what was actually
     * collected via a real payment gateway — a partial/deposit payment (or a
     * Manual cash/UPI payment the property collected directly) can never result
     * in retaining (and crediting the partner) more than the platform actually
     * received. When nothing is capped (the normal full-payment case), this is
     * mathematically identical to the direct "Retained Room × Tax Rate" formula.
     * Uses CommissionService::resolveOnlineCollectedAmount() — the same signal
     * calculateCheckInPartnerCredit() uses — so check-in and cancellation can
     * never disagree about what the platform actually collected for a booking.
     *
     * @return array{refund_amount: float, retained_room: float, retained_tax: float}
     */
    public function calculateCancellationBreakdown(Booking $booking, int $refundPercentage): array
    {
        $refundPercentage = max(0, min(100, $refundPercentage));

        $paymentAmount = app(CommissionService::class)->resolveOnlineCollectedAmount($booking);

        $baseAmount = (float) $booking->base_amount;
        $taxableAmount = max(0.0, $baseAmount - (float) $booking->discount_amount);
        $taxRate = $taxableAmount > 0 ? (float) $booking->tax_amount / $taxableAmount : 0.0;

        $theoreticalRetainedRoom = round($baseAmount * (1 - $refundPercentage / 100), 2);
        $theoreticalRetainedTax = round($theoreticalRetainedRoom * $taxRate, 2);

        $distributable = min($theoreticalRetainedRoom + $theoreticalRetainedTax, $paymentAmount);

        $retainedTax = round($distributable * $taxRate / (1 + $taxRate), 2);
        $retainedRoom = round($distributable - $retainedTax, 2);

        return [
            'refund_amount' => max(0.0, round($paymentAmount - $distributable, 2)),
            'retained_room' => $retainedRoom,
            'retained_tax' => $retainedTax,
        ];
    }

    /**
     * Refund amount for a booking at a given refund percentage. Takes the
     * percentage as a parameter rather than recomputing it via
     * calculateRefundPercentage(), since every caller already has it in hand.
     */
    public function calculateRefundAmount(Booking $booking, int $refundPercentage): float
    {
        return $this->calculateCancellationBreakdown($booking, $refundPercentage)['refund_amount'];
    }

    /**
     * Build and return the snapshot to persist on the booking at creation time.
     * Contains raw rules (for refund calculation) and a display summary (for API responses).
     *
     * @return array{cancellation_cutoff_time: ?string, rules: array<int, array{days_before_checkin: int, refund_percentage: int}>, display: array{free_cancellation_until: ?string, cancellation_cutoff_time: ?string, rules: array<int, array<string, mixed>>}}
     */
    public function buildSnapshot(Property $property, string $checkIn): array
    {
        $policy = $this->resolvePolicyForRefund($property);

        if (! $policy) {
            return [
                'cancellation_cutoff_time' => null,
                'rules' => [],
                'display' => [
                    'free_cancellation_until' => null,
                    'cancellation_cutoff_time' => null,
                    'rules' => [],
                ],
            ];
        }

        $property->loadMissing('country');
        $timezone = $property->timezone ?? $property->country?->timezone ?? 'UTC';

        $policy->load(['rules' => fn ($query) => $query->orderByDesc('days_before_checkin')]);

        $rawRules = $policy->rules
            ->map(fn ($rule) => [
                'days_before_checkin' => (int) $rule->days_before_checkin,
                'refund_percentage' => (int) $rule->refund_percentage,
            ])
            ->values()
            ->toArray();

        return [
            'cancellation_cutoff_time' => $policy->cancellation_cutoff_time,
            'rules' => $rawRules,
            'display' => $this->buildDisplay($policy, $checkIn, $timezone),
        ];
    }

    /**
     * Build a human-readable summary of the cancellation policy for a property and check-in date.
     * Used for pre-booking quote responses. For booked bookings, use the stored snapshot's display field.
     *
     * @return array{free_cancellation_until: ?string, cancellation_cutoff_time: ?string, rules: array<int, array<string, mixed>>}
     */
    public function getSummary(Property $property, string $checkIn): array
    {
        return $this->buildSnapshot($property, $checkIn)['display'];
    }

    /**
     * Build the formatted display portion of the policy snapshot.
     * Uses the property timezone and respects the cutoff time so the display
     * matches what calculateRefundPercentage() would actually compute.
     *
     * @return array{free_cancellation_until: ?string, cancellation_cutoff_time: ?string, rules: array<int, array<string, mixed>>}
     */
    private function buildDisplay(CancellationPolicy $policy, string $checkIn, string $timezone = 'UTC'): array
    {
        $checkInDate = Carbon::parse($checkIn);

        $nowInPropertyTimezone = now()->setTimezone($timezone);
        $effectiveToday = Carbon::today($timezone);

        // If the current time in the property's timezone is past the cancellation cutoff,
        // the earliest eligible cancellation is treated as tomorrow — matching calculateRefundPercentage().
        $cutoffDateTime = $effectiveToday->copy()->setTimeFromTimeString($policy->cancellation_cutoff_time);
        if ($nowInPropertyTimezone->gt($cutoffDateTime)) {
            $effectiveToday->addDay();
        }

        $applicableRules = $policy->rules->filter(function ($rule) use ($checkInDate, $effectiveToday) {
            $deadline = $checkInDate->copy()->subDays((int) $rule->days_before_checkin)->startOfDay();

            return $deadline->gte($effectiveToday);
        });

        if ($applicableRules->isEmpty()) {
            return [
                'free_cancellation_until' => null,
                'cancellation_cutoff_time' => $policy->cancellation_cutoff_time,
                'rules' => [
                    [
                        'refund_percentage' => 0,
                        'label' => 'Non Refundable',
                        'description' => 'Cancellation window has passed. No refund available.',
                    ],
                ],
            ];
        }

        $freeCancellationRule = $applicableRules->firstWhere('refund_percentage', 100);
        $freeCancellationUntil = null;

        if ($freeCancellationRule) {
            $freeCancellationUntil = $checkInDate
                ->copy()
                ->subDays((int) $freeCancellationRule->days_before_checkin)
                ->toDateString();
        }

        return [
            'free_cancellation_until' => $freeCancellationUntil,
            'cancellation_cutoff_time' => $policy->cancellation_cutoff_time,
            'rules' => $this->formatRules($applicableRules, $checkInDate, $freeCancellationUntil),
        ];
    }

    /**
     * Format cancellation policy rules into human-readable date ranges.
     *
     * @return array<int, array{refund_percentage: int, label: string, description: string}>
     */
    private function formatRules(Collection $rules, Carbon $checkInDate, ?string $freeCancellationUntil): array
    {
        if ($rules->isEmpty()) {
            return [];
        }

        $formattedRules = [];
        $sortedRules = $rules->sortByDesc('days_before_checkin')->values();

        foreach ($sortedRules as $index => $rule) {
            $refundPercentage = (int) $rule->refund_percentage;
            $daysBefore = (int) $rule->days_before_checkin;

            $cancellationDate = $checkInDate->copy()->subDays($daysBefore);
            $formattedDate = $cancellationDate->format('M d, Y');

            if ($refundPercentage === 100 && $freeCancellationUntil) {
                $formattedRules[] = [
                    'refund_percentage' => $refundPercentage,
                    'label' => '100% Refund',
                    'description' => "Cancel on or before {$formattedDate}",
                ];

                continue;
            }

            if ($refundPercentage === 0) {
                $formattedRules[] = [
                    'refund_percentage' => $refundPercentage,
                    'label' => 'Non Refundable',
                    'description' => "Cancel on or After {$formattedDate}",
                ];

                continue;
            }

            $endDate = $formattedDate;
            $startDate = $formattedDate;

            if ($index > 0 && isset($sortedRules[$index - 1])) {
                $nextRule = $sortedRules[$index - 1];
                $nextDaysBefore = (int) $nextRule->days_before_checkin;
                $startDate = $checkInDate->copy()->subDays($nextDaysBefore)->format('M d, Y');
            }

            $description = $startDate === $endDate
                ? "Cancel until {$endDate}"
                : "Cancel Between {$startDate} - {$endDate}";

            $formattedRules[] = [
                'refund_percentage' => $refundPercentage,
                'label' => "{$refundPercentage}% Refund",
                'description' => $description,
            ];
        }

        return $formattedRules;
    }

    /**
     * Send an in-app notification and email to every partner whose properties
     * are governed by the given admin default policy (AdminDefault source, same
     * country and property type). Called after any rule or cutoff-time change.
     */
    public function notifyAffectedPartners(CancellationPolicy $policy): void
    {
        // Only admin-default policies (partner_id = null) affect other partners.
        if ($policy->partner_id !== null) {
            return;
        }

        $policy->loadMissing('country');
        $countryName = $policy->country?->name ?? '';

        $properties = Property::query()
            ->with('partner.user')
            ->where('country_id', $policy->country_id)
            ->where('cancellation_policy_source', PropertyCancellationPolicySource::AdminDefault)
            ->whereNotNull('partner_id')
            ->get();

        $affectedUsers = $properties
            ->filter(fn (Property $p) => $p->resolvedPropertyTypeId() === (int) $policy->property_type_id)
            ->map(fn (Property $p) => $p->partner?->user)
            ->filter()
            ->unique('id')
            ->values();

        if ($affectedUsers->isEmpty()) {
            return;
        }

        $userIds = $affectedUsers->pluck('id')->all();

        app(NotificationService::class)->send(
            type: 'cancellation_policy_updated',
            title: __('admin.cancellation_policy_updated_notification_title'),
            body: __('admin.cancellation_policy_updated_notification_body', ['country' => $countryName]),
            userIds: $userIds,
            data: [
                'icon' => 'heroicon-o-document-text',
                'color' => 'warning',
                'country_id' => $policy->country_id,
            ],
            countryId: $policy->country_id,
        );

        foreach ($affectedUsers as $user) {
            $partner = $user->partner;

            if (! $partner) {
                continue;
            }

            Mail::to($user->email)->queue(new CancellationPolicyUpdatedMailable($partner, $countryName));
        }
    }

    /**
     * Resolve which cancellation policy applies to a property for refund calculation.
     *
     * When cancellation_policy_source is Custom, the partner's own policy for the
     * property's country takes priority. Falls back to the admin Country+PropertyType
     * default in all other cases (including when the partner has no policy set yet).
     */
    /**
     * Bypasses PartnerScope for the same reason as getPolicyForProperty() —
     * this is the actual refund-calculation fallback for bookings made before
     * the cancellation_policy_snapshot column existed, so silently failing to
     * find the admin-default policy when a partner triggers the cancellation
     * would have meant a real $0 refund instead of the correct percentage.
     */
    public function resolvePolicyForRefund(Property $property): ?CancellationPolicy
    {
        if (
            $property->cancellation_policy_source === PropertyCancellationPolicySource::Custom
            && $property->partner_id
        ) {
            $partnerPolicy = CancellationPolicy::withoutGlobalScope(PartnerScope::class)
                ->where('country_id', $property->country_id)
                ->where('partner_id', $property->partner_id)
                ->where('is_active', true)
                ->first();

            if ($partnerPolicy) {
                return $partnerPolicy;
            }
        }

        return CancellationPolicy::withoutGlobalScope(PartnerScope::class)
            ->where('country_id', $property->country_id)
            ->whereNull('partner_id')
            ->where('property_type_id', $property->resolvedPropertyTypeId())
            ->where('is_active', true)
            ->first();
    }
}
