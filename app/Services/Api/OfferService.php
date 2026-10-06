<?php

namespace App\Services\Api;

use App\Enums\BookingStatus;
use App\Enums\CouponType;
use App\Enums\CustomerSegment;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoDiscountType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\PromoCode;
use App\Models\Property;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OfferService
{
    /**
     * @param  array{type?: string, scope?: string, limit?: int, offset?: int, property_slug?: string}  $filters
     * @return array<string, mixed>
     */
    public function getOffers(User $user, array $filters = []): array
    {
        $type = (string) ($filters['type'] ?? 'coupon');
        $scope = (string) ($filters['scope'] ?? 'active');
        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $propertySlug = $filters['property_slug'] ?? null;

        $limit = max(1, min($limit, 50));
        $offset = max(0, $offset);

        $page = (int) floor($offset / $limit) + 1;

        $paginator = $type === 'referral'
            ? $this->getReferralOffers($user, $scope, $limit, $page)
            : $this->getPromoOffers($user, $scope, $limit, $page, $propertySlug);

        $items = $type === 'referral'
            ? $this->mapReferralOffers($user, $paginator)
            : $this->mapPromoOffers($paginator);

        return [
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'limit' => $paginator->perPage(),
                'offset' => ($paginator->currentPage() - 1) * $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    private function getPromoOffers(User $user, string $scope, int $limit, int $page, ?string $propertySlug = null): LengthAwarePaginator
    {
        $today = now()->toDateString();
        $hasCompletedBooking = Booking::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::CheckedIn->value,
                BookingStatus::Completed->value,
            ])
            ->exists();

        // When a property_slug is given, derive both country and city from the property
        // so only that property's country coupons are shown, regardless of the user's country.
        if ($propertySlug) {
            $property = Property::query()->where('slug', $propertySlug)->first(['country_id', 'ref_city_id']);
            $countryId = $property?->country_id;
            $refCityId = $property?->ref_city_id;
        } else {
            $countryId = $user->current_country_id ?? $user->country_id;
            $refCityId = null;
        }

        $query = PromoCode::query()
            ->when($countryId, fn ($q) => $q->forCountry((int) $countryId))
            ->where(function ($q) use ($hasCompletedBooking): void {
                $q->where('customer_segment', CustomerSegment::All->value)
                    ->orWhere('customer_segment', $hasCompletedBooking ? CustomerSegment::Returning->value : CustomerSegment::New->value);
            })
            ->when($refCityId, fn ($q) => $q->where(function ($cityQ) use ($refCityId): void {
                $cityQ->whereDoesntHave('cities')
                    ->orWhereHas('cities', fn ($c) => $c->where('ref_city_id', $refCityId));
            }))
            ->with('cities:id,name,ref_city_id')
            ->latest();

        if ($scope === 'inactive') {
            $query->where(function ($q) use ($today) {
                $q->where('is_active', false)
                    ->orWhereDate('start_date', '>', $today)
                    ->orWhereDate('end_date', '<', $today)
                    ->orWhereColumn('used_count', '>=', 'usage_limit');
            });
        } else {
            $query->where('is_active', true)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->whereColumn('used_count', '<', 'usage_limit');
        }

        return $query->paginate(perPage: $limit, page: $page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapPromoOffers(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->map(function (PromoCode $promo): array {
            $status = $promo->status;

            $normalizedStatus = match ($status) {
                PromoCodeStatus::Active => 'active',
                PromoCodeStatus::Expired => 'expired',
                default => 'inactive',
            };

            return [
                'id' => $promo->id,
                'type' => 'coupon',
                'code' => $promo->code,
                'title' => $promo->title,
                'description' => $promo->description,
                'discount_type' => $promo->discount_type->value,
                'discount_value' => (float) $promo->discount_value,
                'discount_label' => $this->formatDiscountLabel($promo->discount_type, (float) $promo->discount_value),
                'max_discount_cap' => $promo->max_discount_cap ? (float) $promo->max_discount_cap : null,
                'min_booking_amount' => $promo->min_booking_amount ? (float) $promo->min_booking_amount : null,
                'status' => $normalizedStatus,
                'expires_at' => $promo->end_date?->toDateString(),
                'is_auto_apply' => (bool) $promo->is_auto_apply,
            ];
        })->values()->toArray();
    }

    private function getReferralOffers(User $user, string $scope, int $limit, int $page): LengthAwarePaginator
    {
        $today = now()->toDateString();

        $query = Coupon::query()
            ->where('user_id', $user->id)
            ->whereIn('source', ['referral_signup', 'referral_reward'])
            ->with(['bookings:id,booking_number,coupon_id,status'])
            ->latest();

        $activeBookingStatuses = [
            BookingStatus::Confirmed->value,
            BookingStatus::CheckedIn->value,
            BookingStatus::Completed->value,
        ];

        if ($scope === 'inactive') {
            $query->where(function ($q) use ($today, $activeBookingStatuses) {
                $q->where('is_used', true)
                    ->orWhereHas('bookings', fn ($b) => $b->whereIn('status', $activeBookingStatuses))
                    ->orWhereDate('expires_at', '<', $today);
            });
        } else {
            $query->where('is_used', false)
                ->whereDoesntHave('bookings', fn ($b) => $b->whereIn('status', $activeBookingStatuses))
                ->where(function ($q) use ($today) {
                    $q->whereNull('expires_at')
                        ->orWhereDate('expires_at', '>=', $today);
                });
        }

        return $query->paginate(perPage: $limit, page: $page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapReferralOffers(User $user, LengthAwarePaginator $paginator): array
    {
        $rewardsForReferee = ReferralReward::query()
            ->where('referee_id', $user->id)
            ->whereNotNull('referee_coupon_id')
            ->with(['referrer:id,name', 'booking:id,booking_number'])
            ->get()
            ->keyBy('referee_coupon_id');

        $rewardsForReferrer = ReferralReward::query()
            ->where('referrer_id', $user->id)
            ->whereNotNull('referrer_coupon_id')
            ->with(['referee:id,name', 'booking:id,booking_number'])
            ->get()
            ->keyBy('referrer_coupon_id');

        return $paginator->getCollection()->map(function (Coupon $coupon) use ($rewardsForReferee, $rewardsForReferrer): array {
            $isUsed = (bool) $coupon->is_used || $coupon->bookings->isNotEmpty();
            $isExpired = ! $isUsed && $coupon->expires_at && $coupon->expires_at->isPast();

            $status = $isUsed ? 'used' : ($isExpired ? 'expired' : 'active');

            $reward = $coupon->source === 'referral_signup'
                ? $rewardsForReferee->get($coupon->id)
                : $rewardsForReferrer->get($coupon->id);

            $bookingNumber = $coupon->bookings->first()?->booking_number
                ?? $reward?->booking?->booking_number;

            $referrerName = $coupon->source === 'referral_signup'
                ? $reward?->referrer?->name
                : $reward?->referee?->name;

            return [
                'id' => $coupon->id,
                'type' => 'referral',
                'code' => $coupon->code,
                'discount_type' => $coupon->type->value,
                'discount_value' => (float) $coupon->value,
                'discount_label' => $this->formatDiscountLabel($coupon->type, (float) $coupon->value),
                'status' => $status,
                'received_at' => $reward?->created_at?->toDateString() ?? $coupon->created_at?->toDateString(),
                'expires_at' => $coupon->expires_at?->toDateString(),
                'referrer_name' => $referrerName,
                'booking_number' => $bookingNumber,
            ];
        })->values()->toArray();
    }

    private function formatDiscountLabel(PromoDiscountType|CouponType $type, float $value): string
    {
        if ($type->value === 'percentage') {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'% Discount';
        }

        return 'Flat '.rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').' Off';
    }
}
