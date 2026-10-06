<?php

namespace App\Services\Api;

use App\Enums\CouponType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate a coupon for a specific user and booking context.
     *
     * @throws ValidationException
     */
    public function validateCoupon(string $code, User $user, float $bookingAmount = 0): Coupon
    {
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon' => 'Invalid coupon code.']);
        }

        // Ownership check
        if ($coupon->user_id !== $user->id) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is not valid for your account.']);
        }

        // Expiry & Usage check
        if (! $coupon->isValid()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon has expired or has already been used.']);
        }

        // First booking only check
        if ($coupon->is_first_booking_only) {
            $hasPreviousBookings = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
                ->exists();

            if ($hasPreviousBookings) {
                throw ValidationException::withMessages(['coupon' => 'This coupon is only valid for your first booking.']);
            }
        }

        // Minimum amount check
        if ($coupon->min_booking_amount && $bookingAmount < $coupon->min_booking_amount) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon requires a minimum booking amount of '.$coupon->min_booking_amount,
            ]);
        }

        return $coupon;
    }

    /**
     * Calculate the discount amount a coupon grants against a total, capped so it
     * can never exceed that total.
     */
    public function calculateDiscount(Coupon $coupon, float $totalAmount): float
    {
        $discount = match ($coupon->type) {
            CouponType::Percentage => round($totalAmount * $coupon->value / 100, 2),
            CouponType::Fixed => round($coupon->value, 2),
        };

        return min($discount, $totalAmount);
    }

    /**
     * Mark a coupon as used.
     */
    public function useCoupon(Coupon $coupon): void
    {
        $coupon->update([
            'is_used' => true,
            'used_at' => now(),
        ]);
    }
}
