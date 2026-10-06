<?php

namespace App\Services\PartnerApi;

use App\Actions\CreatePartnerAction;
use App\Mail\PartnerSubmittedMailable;
use App\Models\OtpVerification;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerVerificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Kreait\Laravel\Firebase\Facades\Firebase;

class PartnerAuthService
{
    const PURPOSE = 'partner_registration';

    public function __construct(
        private CreatePartnerAction $createPartnerAction,
        private PartnerVerificationService $partnerVerificationService,
    ) {}

    /**
     * Complete partner registration after verification.
     *
     * Supports email OTP (verification_token) or phone OTP (firebase_id_token).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        $hasEmail = ! empty($data['email']);
        $hasPhone = ! empty($data['phone']);

        if (! empty($data['verification_token'])) {
            $this->validateVerificationToken($data['verification_token'], $data['email'] ?? '');
        } elseif (! $this->verifyFirebaseToken($data['firebase_id_token'] ?? null)) {
            throw ValidationException::withMessages([
                'firebase_id_token' => 'Invalid Firebase token.',
            ]);
        }

        // Guard against race-condition duplicate registrations
        if ($hasEmail && User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }

        if ($hasPhone) {
            $normalizedDial = $this->normalizeDialCode($data['dial_code'] ?? null);
            if ($this->findUserByPhone($data['phone'], $normalizedDial)) {
                throw ValidationException::withMessages([
                    'phone' => 'This phone number is already registered.',
                ]);
            }
        }

        $partner = $this->createPartnerAction->handle([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'dial_code' => $data['dial_code'] ?? null,
            'password' => Hash::make($data['password']),
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'auth_provider' => $hasEmail ? 'email' : 'phone',
            'firebase_id_token' => $data['firebase_id_token'] ?? null,
        ]);

        $this->partnerVerificationService->maybeAutoApprove($partner);

        if ($partner->user->email) {
            Mail::to($partner->user->email)->queue(new PartnerSubmittedMailable($partner));
        }

        $token = $partner->user->createToken('partner-api')->plainTextToken;

        return [
            'token' => $token,
            'partner' => $this->formatPartnerResponse($partner),
        ];
    }

    private function validateVerificationToken(string $token, string $email): void
    {
        $otpRecord = OtpVerification::query()
            ->where('verification_token', $token)
            ->where('email', $email)
            ->where('purpose', self::PURPOSE)
            ->where('is_verified', true)
            ->first();

        if (! $otpRecord) {
            throw ValidationException::withMessages([
                'verification_token' => 'Invalid or expired verification token.',
            ]);
        }
    }

    private function verifyFirebaseToken(?string $firebaseIdToken): bool
    {
        if (empty($firebaseIdToken)) {
            return false;
        }

        try {
            Firebase::auth()->verifyIdToken($firebaseIdToken);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function normalizeDialCode(?string $dialCode): ?string
    {
        if (empty($dialCode)) {
            return null;
        }

        $stripped = ltrim($dialCode, '+');

        return $stripped !== '' ? '+'.$stripped : null;
    }

    private function findUserByPhone(string $phone, ?string $dialCode = null): ?User
    {
        $query = User::query()->where('phone', $phone);

        if ($dialCode) {
            $query->where('dial_code', $this->normalizeDialCode($dialCode));
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPartnerResponse(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'first_name' => $partner->user->first_name,
            'last_name' => $partner->user->last_name,
            'email' => $partner->user->email,
            'phone' => $partner->user->phone,
            'dial_code' => $partner->user->dial_code,
            'dob' => $partner->user->date_of_birth?->toDateString(),
            'gender' => $partner->user->gender,
            'verification_status' => $partner->verification_status,
        ];
    }
}
