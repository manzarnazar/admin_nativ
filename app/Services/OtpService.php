<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Support\defer;

class OtpService
{
    private const EXPIRY_MINUTES = 5;

    /**
     * Generate a 6-digit OTP for the given email + purpose, store it, and email it.
     *
     * Any prior unverified OTP for the same email + purpose is discarded first.
     */
    public function send(string $email, string $purpose): void
    {
        OtpVerification::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->where('is_verified', false)
            ->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpVerification::create([
            'email' => $email,
            'otp' => $otp,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        $body = "Your OTP code is: {$otp}. It expires in ".self::EXPIRY_MINUTES.' minutes.';

        // Transmit after the HTTP response is sent so the request never blocks on SMTP.
        defer(fn () => Mail::raw($body, function ($message) use ($email) {
            $message->to($email)->subject('Your OTP Code');
        }));
    }

    /**
     * Check whether the submitted code is a valid, unexpired OTP for the email + purpose.
     *
     * This is a pure check — it does NOT mark the code as used, so the same code can be
     * validated more than once within its expiry window (useful when several codes must
     * all pass before any side effect is applied).
     */
    public function verify(string $email, string $code, string $purpose): bool
    {
        $otpRecord = OtpVerification::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if (! $otpRecord || $otpRecord->isExpired()) {
            return false;
        }

        return $otpRecord->otp === $code;
    }

    /**
     * Seconds the caller must wait before a new OTP may be sent for this email + purpose.
     * Returns 0 when a resend is allowed.
     */
    public function secondsUntilResend(string $email, string $purpose, int $cooldown = 30): int
    {
        $lastSentAt = OtpVerification::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest()
            ->value('created_at');

        if (! $lastSentAt) {
            return 0;
        }

        $elapsed = now()->getTimestamp() - $lastSentAt->getTimestamp();

        return (int) max(0, $cooldown - $elapsed);
    }
}
