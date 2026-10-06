<?php

namespace App\Services\Api;

use App\Enums\CouponType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\User;
use App\Support\PercentageValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReferralService
{
    /**
     * Issue a reward coupon to the referee (new user) upon signup.
     */
    public function issueRefereeReward(User $newUser): void
    {
        if (! $newUser->referred_by) {
            return;
        }

        $alreadyIssued = ReferralReward::where('referee_id', $newUser->id)
            ->whereNotNull('referee_coupon_id')
            ->exists();

        if ($alreadyIssued) {
            return;
        }

        $isEnabled = Setting::get('referral_enabled', true);
        if (! $isEnabled) {
            return;
        }

        $percentage = $this->resolvePercentageSetting('referral_referee_percentage', 15);
        $expiryDays = (int) Setting::get('referral_referee_expiry_days', 30);

        $coupon = Coupon::create([
            'code' => $this->generateUniqueCouponCode($newUser->name, 'WELCOME'),
            'user_id' => $newUser->id,
            'type' => CouponType::Percentage,
            'value' => $percentage,
            'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
            'source' => 'referral_signup',
            'is_first_booking_only' => true,
        ]);

        ReferralReward::create([
            'referrer_id' => $newUser->referred_by,
            'referee_id' => $newUser->id,
            'referee_coupon_id' => $coupon->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Issue a reward coupon to the referrer when the referee checks in.
     */
    public function issueReferrerReward(Booking $booking): void
    {
        $referee = $booking->customer;
        if (! $referee || ! $referee->referred_by) {
            return;
        }

        // Check if this is the referee's first check-in to prevent multiple rewards for the same user
        $alreadyRewarded = ReferralReward::where('referee_id', $referee->id)
            ->whereNotNull('referrer_coupon_id')
            ->exists();

        if ($alreadyRewarded) {
            return;
        }

        $isEnabled = Setting::get('referral_enabled', true);
        if (! $isEnabled) {
            return;
        }

        $referrer = User::find($referee->referred_by);
        if (! $referrer) {
            return;
        }

        $percentage = $this->resolvePercentageSetting('referral_referrer_percentage', 10);
        $expiryDays = (int) Setting::get('referral_referrer_expiry_days', 90);

        $coupon = Coupon::create([
            'code' => $this->generateUniqueCouponCode($referrer->name, 'REWARD'),
            'user_id' => $referrer->id,
            'type' => CouponType::Percentage,
            'value' => $percentage,
            'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
            'source' => 'referral_reward',
            'is_first_booking_only' => false,
        ]);

        $referralLog = ReferralReward::where('referee_id', $referee->id)->first();
        if ($referralLog) {
            $referralLog->update([
                'booking_id' => $booking->id,
                'referrer_coupon_id' => $coupon->id,
                'status' => 'success',
            ]);
        }
    }

    /**
     * Read a referral reward percentage from Settings and clamp it to 0-100.
     * This runs inside an automatic signup/check-in flow, not a validated admin
     * form submission, so an out-of-range Setting value is clamped (and logged)
     * rather than thrown — throwing here would break the triggering user flow.
     */
    private function resolvePercentageSetting(string $key, float $default): float
    {
        $raw = (float) Setting::get($key, $default);
        $clamped = PercentageValidator::clamp($raw);

        if ($clamped !== $raw) {
            Log::warning('Referral reward percentage setting out of range, clamped', [
                'setting' => $key,
                'raw' => $raw,
                'clamped' => $clamped,
            ]);
        }

        return $clamped;
    }

    /**
     * Generate a unique, readable coupon code.
     */
    private function generateUniqueCouponCode(string $name, string $suffix): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
        if (empty($prefix)) {
            $prefix = 'GUEST';
        }

        $attempts = 0;
        do {
            $random = strtoupper(Str::random(4));
            $code = "{$prefix}-{$suffix}-{$random}";
            $attempts++;
        } while (Coupon::where('code', $code)->exists() && $attempts < 50);

        return $code;
    }
}
