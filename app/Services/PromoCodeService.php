<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CustomerSegment;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoDiscountType;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\PercentageValidator;
use Illuminate\Validation\ValidationException;

class PromoCodeService
{
    public function create(array $data, int $countryId): PromoCode
    {
        PercentageValidator::assertValidForType($data['discount_type'] ?? null, PromoDiscountType::Percentage->value, $data['discount_value'] ?? null, 'Percentage discount value');

        $cities = $data['city_ids'] ?? [];
        unset($data['city_ids']);

        $data['country_id'] = $countryId;
        $data['code'] = strtoupper($data['code']);

        if (($data['discount_type'] ?? null) === PromoDiscountType::Fixed->value) {
            $data['max_discount_cap'] = null;
        }

        $promo = PromoCode::create($data);

        if (! empty($cities)) {
            $promo->cities()->sync($cities);
        }

        return $promo;
    }

    public function update(PromoCode $promo, array $data): PromoCode
    {
        PercentageValidator::assertValidForType($data['discount_type'] ?? $promo->discount_type, PromoDiscountType::Percentage->value, $data['discount_value'] ?? null, 'Percentage discount value');

        $cities = $data['city_ids'] ?? [];
        unset($data['city_ids']);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        if (($data['discount_type'] ?? null) === PromoDiscountType::Fixed->value) {
            $data['max_discount_cap'] = null;
        }

        $promo->update($data);
        $promo->cities()->sync($cities);

        return $promo;
    }

    public function delete(PromoCode $promo): void
    {
        $promo->cities()->detach();
        $promo->delete();
    }

    public function findAutoApplyPromo(User $user, float $totalAmount, int $countryId, ?int $refCityId = null): ?PromoCode
    {
        $candidates = PromoCode::query()
            ->where('is_active', true)
            ->where('is_auto_apply', true)
            ->where('country_id', $countryId)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->whereColumn('used_count', '<', 'usage_limit')
            ->where(fn ($q) => $q
                ->whereNull('min_booking_amount')
                ->orWhere('min_booking_amount', '<=', $totalAmount)
            )
            ->with(['cities' => fn ($q) => $q->withTrashed()])
            ->get();

        $hasCompletedBooking = $user->bookings()
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::CheckedIn->value,
                BookingStatus::Completed->value,
            ])
            ->exists();

        return $candidates->first(function (PromoCode $promo) use ($refCityId, $hasCompletedBooking): bool {
            if ($promo->cities->isNotEmpty()) {
                if ($refCityId === null || $promo->cities->where('ref_city_id', $refCityId)->isEmpty()) {
                    return false;
                }
            }

            if ($promo->customer_segment === CustomerSegment::New && $hasCompletedBooking) {
                return false;
            }

            if ($promo->customer_segment === CustomerSegment::Returning && ! $hasCompletedBooking) {
                return false;
            }

            if ($promo->is_first_booking_only && $hasCompletedBooking) {
                return false;
            }

            return true;
        });
    }

    public function validatePromoCode(string $code, User $user, float $totalAmount, int $countryId, ?int $refCityId = null): PromoCode
    {
        $promo = PromoCode::query()
            ->where('code', strtoupper($code))
            ->where('country_id', $countryId)
            ->with(['cities' => fn ($q) => $q->withTrashed()])
            ->first();

        if (! $promo) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Invalid promo code.',
            ]);
        }

        if (! $promo->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This promo code is not active.',
            ]);
        }

        if ($promo->end_date->lt(today())) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This promo code has expired.',
            ]);
        }

        if ($promo->start_date->gt(today())) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This promo code is not yet active.',
            ]);
        }

        if ($promo->used_count >= $promo->usage_limit) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This promo code has reached its usage limit.',
            ]);
        }

        if ($promo->min_booking_amount && $totalAmount < $promo->min_booking_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Minimum booking amount not met for this promo code.',
            ]);
        }

        // Check city restrictions
        if ($promo->cities->isNotEmpty()) {
            if ($refCityId === null || $promo->cities->where('ref_city_id', $refCityId)->isEmpty()) {
                throw ValidationException::withMessages([
                    'coupon_code' => 'This promo code is not valid for this city.',
                ]);
            }
        }

        // Check customer segment
        $hasCompletedBooking = $user->bookings()
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::CheckedIn->value,
                BookingStatus::Completed->value,
            ])
            ->exists();

        if ($promo->customer_segment === CustomerSegment::New && $hasCompletedBooking) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This promo code is only for new customers.',
            ]);
        }

        if ($promo->customer_segment === CustomerSegment::Returning && ! $hasCompletedBooking) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This promo code is only for returning customers.',
            ]);
        }

        if ($promo->is_first_booking_only && $hasCompletedBooking) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This promo code is only valid for your first booking.',
            ]);
        }

        return $promo;
    }

    public function calculateDiscount(PromoCode $promo, float $totalAmount): float
    {
        $discount = match ($promo->discount_type) {
            PromoDiscountType::Percentage => round($totalAmount * $promo->discount_value / 100, 2),
            PromoDiscountType::Fixed => round($promo->discount_value, 2),
        };

        if ($promo->max_discount_cap !== null) {
            $discount = min($discount, (float) $promo->max_discount_cap);
        }

        return min($discount, $totalAmount);
    }

    public function getStats(int $countryId): array
    {
        $promos = PromoCode::query()->forCountry($countryId)->get();

        $activeCount = $promos->filter(
            fn (PromoCode $p) => $p->status === PromoCodeStatus::Active
        )->count();

        $totalRedemptions = $promos->sum('used_count');

        $discountValueGiven = Booking::query()
            ->whereIn('promo_code_id', $promos->pluck('id'))
            ->sum('discount_amount');

        return [
            'active_count' => $activeCount,
            'total_redemptions' => $totalRedemptions,
            'discount_value_given' => $discountValueGiven,
        ];
    }
}
