<?php

namespace App\Services\Api;

use App\Enums\LoginFailureReason;
use App\Enums\LoginStatus;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\LoginAlertMailable;
use App\Models\Notification;
use App\Models\OtpVerification;
use App\Models\SocialLogin;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\LoginLogService;
use App\Services\OtpService;
use App\Support\DemoMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kreait\Laravel\Firebase\Facades\Firebase;

class AuthService
{
    public function __construct(private LoginLogService $loginLogService) {}

    /**
     * "Mobile App Android" style label built from the client-reported platform — API/mobile
     * requests don't carry a real browser User-Agent, so LoginLogService's UA parsing wouldn't
     * produce anything meaningful for them.
     */
    private function apiDeviceLabel(?string $platform): ?string
    {
        return $platform ? 'Mobile App '.ucfirst($platform) : null;
    }

    // ─────────────────────────────────────────────
    // OTP
    // ─────────────────────────────────────────────

    /**
     * Send OTP to email. Used for registration, password reset, and profile update.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendEmailOtp(array $data): array
    {
        $email = $data['email'];
        $purpose = $data['purpose'];

        // For password_reset, don't reveal if user exists
        if ($purpose === 'password_reset') {
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                $this->sendOtp($email, $purpose);
            }

            return [
                'type' => 'email',
                'expires_in' => 300,
                'resend_after' => 25,
            ];
        }

        // For partner_registration, reject early if email is already taken
        if ($purpose === 'partner_registration') {
            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already registered.',
                ]);
            }

            return $this->sendOtp($email, $purpose);
        }

        // For registration and profile_update, just send
        return $this->sendOtp($email, $purpose);
    }

    /**
     * Verify OTP and return a verification token.
     * Shared by registration, forgot password, and profile update flows.
     *
     * @return array<string, mixed>
     */
    public function verifyOtp(string $identifier, string $otp, string $purpose): array
    {
        $otpRecord = OtpVerification::query()
            ->where('email', $identifier)
            ->where('purpose', $purpose)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if (! $otpRecord) {
            return ['verified' => false, 'message' => 'No OTP found for this email'];
        }

        if ($otpRecord->isExpired()) {
            return ['verified' => false, 'message' => 'OTP has expired'];
        }

        if ($otpRecord->otp !== $otp) {
            return ['verified' => false, 'message' => 'Invalid OTP code'];
        }

        $verificationToken = Str::random(64);

        $otpRecord->update([
            'is_verified' => true,
            'verification_token' => $verificationToken,
        ]);

        return [
            'verified' => true,
            'verification_token' => $verificationToken,
        ];
    }

    /**
     * Step 3: Complete registration after OTP verification.
     * Account is created here.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function registerComplete(array $data): array
    {
        // Validate verification
        if (! empty($data['verification_token'])) {
            $this->validateVerificationToken($data['verification_token'], $data['email'] ?? '', 'registration');
        } elseif (! $this->verifyFirebaseToken($data['firebase_id_token'] ?? null)) {
            throw ValidationException::withMessages([
                'firebase_id_token' => 'Invalid Firebase token.',
            ]);
        }

        // Double-check uniqueness (prevent race conditions)
        if (! empty($data['email']) && User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }

        if (! empty($data['phone'])) {
            $countryCode = $this->normalizeDialCode($data['dial_code'] ?? null);
            if ($this->findUserByPhone($data['phone'], $countryCode)) {
                throw ValidationException::withMessages([
                    'phone' => 'This phone number is already registered.',
                ]);
            }
        }

        // Look up referrer
        $referrerId = null;
        if (! empty($data['referral_code'])) {
            $referrer = User::query()->where('referral_code', $data['referral_code'])->first();
            if ($referrer) {
                $referrerId = $referrer->id;
            }
        }

        $hasEmail = ! empty($data['email']);
        $hasPhone = ! empty($data['phone']);
        $authProvider = $hasEmail ? 'email' : 'phone';

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'dial_code' => $hasPhone ? $this->normalizeDialCode($data['dial_code'] ?? null) : null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'auth_provider' => $authProvider,
            'platform' => $data['platform'] ?? null,
            'email_verified_at' => (! empty($data['verification_token']) && $hasEmail) ? now() : null,
            'phone_verified_at' => $this->verifyFirebaseToken($data['firebase_id_token'] ?? null) ? now() : null,
            'referral_code' => $this->generateReferralCode($data['name']),
            'referred_by' => $referrerId,
        ]);

        $this->createDefaultNotificationPreferences($user);

        // Issue referral reward if applicable
        app(ReferralService::class)->issueRefereeReward($user);

        $token = $user->createToken('default')->plainTextToken;

        if (! empty($data['fcm_token'])) {
            $this->storeFcmToken($user, $data['fcm_token']);
        }

        return [
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ];
    }

    // ─────────────────────────────────────────────
    // Login
    // ─────────────────────────────────────────────

    /**
     * Login with email/phone + password.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function login(array $data): array
    {
        $type = $this->detectIdentifierType($data['identifier']);

        if ($type === 'phone') {
            $user = $this->findUserByPhone($data['identifier'], $data['dial_code'] ?? null);
        } else {
            $user = User::query()->where('email', $data['identifier'])->first();
        }

        // Same error for "not found" and "wrong password" to prevent enumeration
        if (! $user || is_null($user->password) || ! Hash::check($data['password'], $user->password)) {
            $this->loginLogService->record($user, $data['identifier'], LoginStatus::Failed, $this->apiDeviceLabel($data['platform'] ?? null), reason: LoginFailureReason::InvalidCredentials);

            throw ValidationException::withMessages([
                'identifier' => 'Invalid credentials.',
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            $this->loginLogService->record($user, $data['identifier'], LoginStatus::Failed, $this->apiDeviceLabel($data['platform'] ?? null), reason: LoginFailureReason::AccountInactive);

            throw ValidationException::withMessages([
                'identifier' => 'Your account is '.$user->status->value.'. Please contact support.',
            ]);
        }

        $this->loginLogService->record($user, $data['identifier'], LoginStatus::Success, $this->apiDeviceLabel($data['platform'] ?? $user->platform));

        $token = $user->createToken('default')->plainTextToken;

        if (! empty($data['fcm_token'])) {
            $this->storeFcmToken($user, $data['fcm_token']);
        }

        // Update platform if not set (admin-created users logging in for first time)
        $updateData = ['last_login_at' => now()];
        if (! $user->platform && ! empty($data['platform'])) {
            $updateData['platform'] = $data['platform'];
        }
        $user->update($updateData);

        $this->sendLoginAlert($user, 'Password');

        return [
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ];
    }

    // ─────────────────────────────────────────────
    // Social Login (Google / Apple)
    // ─────────────────────────────────────────────

    /**
     * Social login/register via Firebase ID token (Google or Apple).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function socialLogin(array $data): array
    {
        $provider = $data['provider'];

        try {
            $auth = Firebase::auth();
            $verifiedToken = $auth->verifyIdToken($data['id_token']);
        } catch (\Exception $e) {
            $this->loginLogService->record(null, $data['email'] ?? ucfirst($provider).' login', LoginStatus::Failed, $this->apiDeviceLabel($data['platform'] ?? null), reason: LoginFailureReason::InvalidToken);

            throw ValidationException::withMessages([
                'id_token' => 'Invalid '.ucfirst($provider).' token.',
            ]);
        }

        $firebaseUid = $verifiedToken->claims()->get('sub');
        $email = $verifiedToken->claims()->get('email');
        $name = $data['name'] ?? $verifiedToken->claims()->get('name');

        return $this->handleSocialLogin(
            provider: $provider,
            providerId: $firebaseUid,
            email: $email,
            name: $name,
            data: $data,
        );
    }

    /**
     * Shared logic for social login (Google/Apple).
     * Creates account immediately if new user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleSocialLogin(
        string $provider,
        string $providerId,
        ?string $email,
        ?string $name,
        array $data,
    ): array {
        $fcmToken = $data['fcm_token'] ?? null;
        $platform = $data['platform'] ?? null;
        $isNewUser = false;

        // Check if this social account is already linked
        $socialLogin = SocialLogin::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($socialLogin) {
            $user = $socialLogin->user;

            if ($user->auth_provider !== $provider) {
                $user->update(['auth_provider' => $provider]);
            }
        } else {
            // Check if a user with this email already exists
            $user = $email ? User::query()->where('email', $email)->first() : null;

            if ($user) {
                if ($user->role !== UserRole::Customer) {
                    $this->loginLogService->record($user, $email ?? ucfirst($provider).' login', LoginStatus::Failed, $this->apiDeviceLabel($platform), reason: LoginFailureReason::RoleMismatch);

                    throw ValidationException::withMessages([
                        'email' => 'This email is registered as an Admin/Staff account. Please use a different email for the customer app.',
                    ]);
                }
                $user->update(['auth_provider' => $provider]);
            } else {
                try {
                    DB::transaction(function () use ($name, $email, $provider, $platform, $providerId, &$user, &$isNewUser) {
                        $user = User::create([
                            'name' => $name ?? 'User',
                            'email' => $email,
                            'role' => UserRole::Customer,
                            'status' => UserStatus::Active,
                            'auth_provider' => $provider,
                            'platform' => $platform,
                            'email_verified_at' => $email ? now() : null,
                            'referral_code' => $this->generateReferralCode($name),
                        ]);

                        SocialLogin::create([
                            'user_id' => $user->id,
                            'provider' => $provider,
                            'provider_id' => $providerId,
                            'provider_email' => $email,
                        ]);

                        $this->createDefaultNotificationPreferences($user);

                        $isNewUser = true;
                    });
                } catch (QueryException $e) {
                    // Race condition: another request created the user between our check and insert
                    if ($e->getCode() === '23000' && $email) {
                        $user = User::query()->where('email', $email)->firstOrFail();
                        $user->update(['auth_provider' => $provider]);

                        SocialLogin::query()->updateOrCreate(
                            ['provider' => $provider, 'provider_id' => $providerId],
                            ['user_id' => $user->id, 'provider_email' => $email],
                        );
                    } else {
                        throw $e;
                    }
                }
            }
        }

        if ($user->status !== UserStatus::Active) {
            $this->loginLogService->record($user, $email ?? ucfirst($provider).' login', LoginStatus::Failed, $this->apiDeviceLabel($platform), reason: LoginFailureReason::AccountInactive);

            throw ValidationException::withMessages([
                'email' => 'Your account is '.$user->status->value.'. Please contact support.',
            ]);
        }

        $this->loginLogService->record($user, $email ?? ucfirst($provider).' login', LoginStatus::Success, $this->apiDeviceLabel($platform ?? $user->platform));

        $token = $user->createToken('default')->plainTextToken;

        if ($fcmToken) {
            $this->storeFcmToken($user, $fcmToken);
        }

        $updateData = ['last_login_at' => now()];
        if (! $user->platform && $platform) {
            $updateData['platform'] = $platform;
        }
        $user->update($updateData);

        // Skip the alert for brand-new accounts — they were just created this request
        // and a welcome email is more appropriate than a security alert.
        if (! $isNewUser) {
            $this->sendLoginAlert($user, ucfirst($provider));
        }

        return [
            'user' => $this->formatUserResponse($user),
            'token' => $token,
            'is_new_user' => $isNewUser,
        ];
    }

    // ─────────────────────────────────────────────
    // Reset Password
    // ─────────────────────────────────────────────

    /**
     * Reset password after OTP verification.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resetPassword(array $data): array
    {
        $type = $this->detectIdentifierType($data['identifier']);

        if (! empty($data['verification_token'])) {
            $this->validateVerificationToken($data['verification_token'], $data['identifier'], 'password_reset');
        } elseif (! $this->verifyFirebaseToken($data['firebase_id_token'] ?? null)) {
            throw ValidationException::withMessages([
                'firebase_id_token' => 'Invalid Firebase token.',
            ]);
        }

        if ($type === 'phone') {
            $user = $this->findUserByPhone($data['identifier'], $data['dial_code'] ?? null);
        } else {
            $user = User::query()->where('email', $data['identifier'])->first();
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'identifier' => 'Invalid credentials.',
            ]);
        }

        if (DemoMode::isActive($user)) {
            activity('security')
                ->causedBy($user)
                ->event('demo_password_reset_blocked')
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'identifier' => $data['identifier'],
                ])
                ->log('Demo account API password reset attempt blocked.');

            throw ValidationException::withMessages([
                'identifier' => __('admin.demo_account_action_not_allowed'),
            ]);
        }

        // Check if new password is same as old password
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'You cannot use your old password. Please choose a different password.',
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return [
        ];
    }

    /**
     * Send a "new sign-in" security alert email to the user.
     * Queued so the login API response isn't blocked. Skipped silently
     * if the account has no email (e.g. phone-only signup).
     */
    private function sendLoginAlert(User $user, string $method): void
    {
        if (! $user->email) {
            return;
        }

        Mail::to($user->email)->queue(new LoginAlertMailable($user, $method, CarbonImmutable::now()));
    }

    // ─────────────────────────────────────────────
    // Logout / User Profile
    // ─────────────────────────────────────────────

    /**
     * Logout — revoke current device token and remove its FCM token.
     */
    public function logout(User $user, ?string $fcmToken = null): void
    {
        if ($fcmToken) {
            $user->fcmTokens()->where('token', $fcmToken)->delete();
        }

        $user->currentAccessToken()->delete();
    }

    /**
     * Change password for authenticated user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function changePassword(User $user, array $data): array
    {
        if (is_null($user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'No password set. Use forgot password to set one first.',
            ]);
        }

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return [
        ];
    }

    /**
     * Update profile (name and/or avatar).
     *
     * @return array<string, mixed>
     */
    public function updateProfile(User $user, array $data, $avatarFile = null): array
    {
        $updateData = [];

        // ── Email update ──
        if (array_key_exists('email', $data)) {
            $email = $data['email'];

            if (empty($email)) {
                // Removing email
                if ($user->auth_provider === 'email') {
                    throw ValidationException::withMessages([
                        'email' => 'You cannot remove your primary email address.',
                    ]);
                }
                $updateData['email'] = null;
                $updateData['email_verified_at'] = null;
            } elseif ($email !== $user->email) {
                // Changing email
                if ($user->auth_provider === 'email') {
                    throw ValidationException::withMessages([
                        'email' => 'You cannot change your primary email address.',
                    ]);
                }

                if (empty($data['verification_token'])) {
                    throw ValidationException::withMessages([
                        'verification_token' => 'Verification token is required to update email.',
                    ]);
                }

                $this->validateVerificationToken($data['verification_token'], $email, 'profile_update');

                if (User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => 'This email is already in use by another account.',
                    ]);
                }

                $updateData['email'] = $email;
                $updateData['email_verified_at'] = now();
            }
        }

        // ── Phone update ──
        if (array_key_exists('phone', $data)) {
            $phone = $data['phone'];

            if (empty($phone)) {
                // Removing phone
                if ($user->auth_provider === 'phone') {
                    throw ValidationException::withMessages([
                        'phone' => 'You cannot remove your primary phone number.',
                    ]);
                }
                $updateData['phone'] = null;
                $updateData['dial_code'] = null;
                $updateData['phone_verified_at'] = null;
            } elseif ($phone !== $user->phone) {
                // Changing phone
                if ($user->auth_provider === 'phone') {
                    throw ValidationException::withMessages([
                        'phone' => 'You cannot change your primary phone number.',
                    ]);
                }

                if (empty($data['firebase_id_token'])) {
                    throw ValidationException::withMessages([
                        'firebase_id_token' => 'Firebase ID token is required to update phone.',
                    ]);
                }

                if (! $this->verifyFirebaseToken($data['firebase_id_token'])) {
                    throw ValidationException::withMessages([
                        'firebase_id_token' => 'Invalid Firebase token.',
                    ]);
                }

                $countryCode = $this->normalizeDialCode($data['dial_code'] ?? null);

                if (User::query()->where('phone', $phone)->where('dial_code', $countryCode)->where('id', '!=', $user->id)->exists()) {
                    throw ValidationException::withMessages([
                        'phone' => 'This phone number is already in use by another account.',
                    ]);
                }

                $updateData['phone'] = $phone;
                $updateData['dial_code'] = $countryCode;
                $updateData['country_code'] = $data['country_code'] ?? null;
                $updateData['phone_verified_at'] = now();
            }
        }

        // ── Name ──
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        // ── Avatar ──
        if ($avatarFile) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $updateData['avatar'] = $avatarFile->store('avatars', 'public');
        }

        // ── Referral code ──
        $shouldIssueRefereeReward = false;

        if (isset($data['referral_code']) && ! $user->referred_by) {
            $referrer = User::query()->where('referral_code', $data['referral_code'])->first();
            if ($referrer && $referrer->id !== $user->id) {
                $updateData['referred_by'] = $referrer->id;
                $shouldIssueRefereeReward = true;
            }
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        if ($shouldIssueRefereeReward) {
            app(ReferralService::class)->issueRefereeReward($user->fresh());
        }

        return [
            'user' => $this->formatUserResponse($user->fresh()),
        ];
    }

    /**
     * Delete account after re-authentication.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function deleteAccount(User $user, array $data): array
    {
        // Re-authenticate
        if (! empty($data['provider'])) {
            // Social user — verify auth_provider matches
            if ($user->auth_provider !== $data['provider']) {
                throw ValidationException::withMessages([
                    'provider' => 'This account was not registered with '.$data['provider'].'.',
                ]);
            }
        } else {
            // Password user
            if (is_null($user->password) || ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Invalid password.',
                ]);
            }
        }

        // PREVENT DELETION FOR ADMINS/STAFF
        if ($user->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'role' => 'Admin or Staff accounts cannot be deleted from the mobile app. Please contact the main administrator.',
            ]);
        }

        // Suffix email/phone to free unique constraints
        if ($user->email) {
            $user->email = $user->email.'_deleted_'.$user->id;
        }
        if ($user->phone) {
            $user->phone = $user->phone.'_deleted_'.$user->id;
        }
        $user->status = UserStatus::Inactive;
        $user->save();

        // Revoke all tokens (logout from all devices)
        $user->tokens()->delete();

        // Delete all FCM tokens
        $user->fcmTokens()->delete();

        // Soft delete
        $user->delete();

        return [
        ];
    }

    /**
     * Update/Store FCM token for the authenticated user.
     */
    public function updateFcmToken(User $user, string $token): void
    {
        $this->storeFcmToken($user, $token);
    }

    /**
     * Get the authenticated user's notification preferences.
     *
     * @return array<string, mixed>
     */
    public function getNotificationPreferences(User $user): array
    {
        $categories = NotificationCategory::cases();
        $preferences = $user->notificationPreferences->mapWithKeys(function ($pref) {
            return [$pref->category->value => $pref->is_enabled];
        })->toArray();

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'category' => $category->value,
                'label' => $category->label(),
                'is_enabled' => $preferences[$category->value] ?? true, // Default to true if no preference record exists
            ];
        }

        return [
            'preferences' => $result,
        ];
    }

    /**
     * Update notification preferences for the authenticated user.
     *
     * @param  array<string, bool>  $preferences
     */
    public function updateNotificationPreferences(User $user, array $preferences): void
    {
        foreach ($preferences as $category => $isEnabled) {
            // Validate category
            $enumCategory = NotificationCategory::tryFrom($category);
            if (! $enumCategory) {
                continue;
            }

            $user->notificationPreferences()->updateOrCreate(
                ['category' => $enumCategory],
                ['is_enabled' => (bool) $isEnabled]
            );
        }
    }

    /**
     * Get the authenticated user's profile.
     *
     * @return array<string, mixed>
     */
    public function getUser(User $user): array
    {
        return [
            'user' => $this->formatUserResponse($user),
        ];
    }

    // ─────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────

    /**
     * Generate and send OTP to the given email.
     *
     * @return array<string, mixed>
     */
    private function sendOtp(string $email, string $purpose): array
    {
        app(OtpService::class)->send($email, $purpose);

        return [
            'type' => 'email',
            'expires_in' => 300,
            'resend_after' => 25,
        ];
    }

    /**
     * Validate a verification token from OTP verification.
     */
    private function validateVerificationToken(string $token, string $identifier, string $purpose): void
    {
        $otpRecord = OtpVerification::query()
            ->where('verification_token', $token)
            ->where('email', $identifier)
            ->where('purpose', $purpose)
            ->where('is_verified', true)
            ->first();

        if (! $otpRecord) {
            throw ValidationException::withMessages([
                'verification_token' => 'Invalid or expired verification token.',
            ]);
        }
    }

    /**
     * Verify a Firebase ID token.
     */
    private function verifyFirebaseToken(?string $firebaseIdToken): bool
    {
        if (empty($firebaseIdToken)) {
            return false;
        }

        try {
            $auth = Firebase::auth();
            $auth->verifyIdToken($firebaseIdToken);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Store or update FCM token for push notifications.
     */
    private function storeFcmToken(User $user, string $fcmToken): void
    {
        $user->fcmTokens()->updateOrCreate(
            ['token' => $fcmToken],
        );
    }

    /**
     * Format user data for API response.
     *
     * @return array<string, mixed>
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'country_code' => $user->country_code,
            'dial_code' => $user->dial_code,
            'phone' => $user->phone,
            'is_demo_account' => $user->isDemoAccount(),
            'profile' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'role' => $user->role?->value,
            'status' => $user->status === UserStatus::Active ? 1 : 0,
            'locale' => $user->locale,
            'auth_provider' => $user->auth_provider,
            'platform' => $user->platform,
            'referral_code' => $user->referral_code,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'phone_verified_at' => $user->phone_verified_at?->toISOString(),
            'social_logins' => $user->socialLogins->pluck('provider')->toArray(),
        ];
    }

    /**
     * Generate a unique referral code for the user.
     */
    private function generateReferralCode(?string $name = null): string
    {
        $prefix = '';
        if ($name) {
            // Remove non-alphanumeric characters and lowercase
            $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
            $prefix = substr($cleanName, 0, 4);
        }

        // If name is empty or results in empty prefix, use random letters
        if (empty($prefix)) {
            $prefix = strtolower(Str::random(4));
        }

        $totalLength = 8;
        $attempts = 0;

        do {
            // If many collisions occur, fallback to random prefix
            if ($attempts > 5) {
                $prefix = strtolower(Str::random(4));
            }

            $suffixLength = $totalLength - strlen($prefix);
            $suffix = '';
            for ($i = 0; $i < $suffixLength; $i++) {
                $suffix .= (string) random_int(0, 9);
            }
            $code = $prefix.$suffix;
            $attempts++;
        } while (User::query()->where('referral_code', $code)->exists() && $attempts < 100);

        return $code;
    }

    /**
     * Detect if the identifier is an email or phone number.
     */
    private function detectIdentifierType(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    /**
     * Normalize dial code — ensure it starts with +.
     */
    private function normalizeDialCode(?string $countryCode): ?string
    {
        if (empty($countryCode)) {
            return null;
        }

        $stripped = ltrim($countryCode, '+');

        return $stripped !== '' ? '+'.$stripped : null;
    }

    /**
     * Find a user by phone number (dial_code + phone).
     */
    private function findUserByPhone(string $phone, ?string $dialCode = null): ?User
    {
        $query = User::query()->where('phone', $phone);

        if ($dialCode) {
            $query->where('dial_code', $this->normalizeDialCode($dialCode));
        }

        return $query->first();
    }

    private function createDefaultNotificationPreferences(User $user): void
    {
        $preferences = collect(NotificationCategory::cases())->map(fn ($category) => [
            'user_id' => $user->id,
            'category' => $category->value,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        UserNotificationPreference::insert($preferences);
    }
}
