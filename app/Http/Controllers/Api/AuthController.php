<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\DeleteAccountRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterCompleteRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\SendEmailOtpRequest;
use App\Http\Requests\Api\Auth\SocialLoginRequest;
use App\Http\Requests\Api\Auth\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Auth\VerifyOtpRequest;
use App\Services\Api\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
    ) {}

    /**
     * Send email OTP.
     *
     * Send OTP to an email address. The `purpose` field determines the context:
     *
     * - `registration` — verify email before creating account.
     * - `password_reset` — verify email before resetting password. For security, response is the same whether email exists or not.
     * - `profile_update` — verify new email before updating profile.
     * - `partner_registration` - verify email for partner registration (separate from user registration).
     *
     * OTP is valid for 5 minutes. Resend is allowed after 25 seconds.
     *
     *
     * ```
     * {
  "email": "bhavikbhuva81@gmail.com",
  "purpose": "partner_registration"
}
     */
    public function sendEmailOtp(SendEmailOtpRequest $request): JsonResponse
    {
        $result = $this->authService->sendEmailOtp($request->validated());

        $message = $request->validated('purpose') === 'password_reset'
            ? 'If an account exists with this email, an OTP has been sent.'
            : 'OTP sent successfully';

        return $this->successResponse($result, $message);
    }

    /**
     * Verify OTP.
     *
     * Verify the OTP sent to the user. The `purpose` must match what was used in `send-email-otp`:
     *
     * - `registration` — customer account registration.
     * - `password_reset` — password reset flow.
     * - `profile_update` — email change flow.
     * - `partner_registration` — partner account registration.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authService->verifyOtp(
            $request->validated('identifier'),
            $request->validated('otp'),
            $request->validated('purpose'),
        );

        if (! $result['verified']) {
            return $this->errorResponse($result['message'], 422);
        }

        return $this->successResponse($result, 'OTP verified successfully');
    }

    /**
     * Complete registration.
     *
     * Create account after OTP verification.
     *
     * - Email registration: send `verification_token` from the OTP verify step.
     * - Phone registration: send `firebase_id_token` from Firebase phone verification + `dial_code`.
     * - `platform` should be `android`, `ios`, or `web` — stored permanently to track registration source.
     * - `referral_code` is optional — if provided, links the new user to the referrer.
     */
    public function registerComplete(RegisterCompleteRequest $request): JsonResponse
    {
        $result = $this->authService->registerComplete($request->validated());

        return $this->successResponse($result, 'Registration successful', 201);
    }

    /**
     * Login.
     *
     * Login with email/phone and password.
     *
     * - `identifier` can be either an email or a phone number — backend auto-detects.
     * - When using phone, also send `dial_code` (e.g., `+91`) and optionally `country_code` (e.g., `IN`).
     * - Returns the same `Invalid credentials` error for wrong email, wrong password, or non-existent account (security).
     * - Social login users without a password will also get `Invalid credentials` — they should use `social/login` or set a password via forgot password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->successResponse($result, 'Login successful');
    }

    /**
     * Social login (Google/Apple).
     *
     * Login or register using a social provider. The `name` field is only required
     * for Apple on the first login — Apple shares the user's name only once.
     * For Google, the name is always available in the token.
     */
    public function socialLogin(SocialLoginRequest $request): JsonResponse
    {
        $result = $this->authService->socialLogin($request->validated());

        $message = ($result['is_new_user'] ?? false) ? 'Registration successful' : 'Login successful';

        return $this->successResponse($result, $message);
    }

    /**
     * Reset password.
     *
     * Set a new password after OTP verification.
     *
     * - Email reset: first call `send-email-otp` (purpose: `password_reset`), then `otp/verify`, then this endpoint with `verification_token`.
     * - Phone reset: frontend verifies phone via Firebase, then call this endpoint with `firebase_id_token` + `dial_code`.
     * - User must login again after reset — no auto-login.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->resetPassword($request->validated());

        return $this->successResponse($result, 'Password reset successfully');
    }

    /**
     * Logout.
     *
     * Revoke the current device token. Optionally pass `fcm_token` to remove push notification token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), $request->input('fcm_token'));

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * Get user profile.
     *
     * Returns the authenticated user's profile including social logins and referral code.
     */
    public function user(Request $request): JsonResponse
    {
        $result = $this->authService->getUser($request->user());

        return $this->successResponse($result, 'User fetched successfully');
    }

    /**
     * Update profile.
     *
     * Unified endpoint for all profile updates. Send only the fields you want to update.
     * If any field fails validation, nothing is updated (all or nothing).
     *
     * **Simple fields (no verification needed):**
     * - `name` — update display name
     * - `profile` — upload new profile picture (send as `multipart/form-data`, max 2MB, any image type)
     * - `referral_code` — set referrer code
     *
     * **Email (requires OTP verification):**
     * - To change: send `email` + `verification_token` (from `send-email-otp` → `otp/verify` flow)
     * - To remove: send `email` as empty string `""`
     * - Blocked if `auth_provider` is `email` (primary identifier cannot be changed/removed)
     *
     * **Phone (requires Firebase verification):**
     * - To change: send `phone` + `dial_code` + `firebase_id_token`
     * - To remove: send `phone` as empty string `""`
     * - Blocked if `auth_provider` is `phone` (primary identifier cannot be changed/removed)
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $result = $this->authService->updateProfile(
            $request->user(),
            $request->validated(),
            $request->file('profile'),
        );

        return $this->successResponse($result, 'Profile updated successfully');
    }

    /**
     * Change password.
     *
     * Change password for authenticated user. Requires current password.
     * Social login users without a password must use forgot password first.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $result = $this->authService->changePassword($request->user(), $request->validated());

        return $this->successResponse($result, 'Password changed successfully');
    }

    /**
     * Delete account.
     *
     * Permanently deletes the user's account. This action is irreversible.
     *
     * - For email/phone users: send `password` to confirm.
     * - For social login users (Google/Apple): send `provider` (e.g., `google` or `apple`). Frontend must re-authenticate with the social provider before calling this.
     *
     * What happens on deletion:
     * - Account is soft-deleted (data preserved for admin/financial records)
     * - Email/phone are freed for new registrations
     * - All sessions are revoked (logged out from all devices)
     * - All push notification tokens are removed
     */
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $result = $this->authService->deleteAccount($request->user(), $request->validated());

        return $this->successResponse($result, 'Account deleted successfully');
    }

    /**
     * Update FCM token.
     *
     * Store or update the push notification token for the current device.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $this->authService->updateFcmToken($request->user(), $request->input('fcm_token'));

        return $this->successResponse(null, 'FCM token updated successfully');
    }

    /**
     * Get notification preferences.
     *
     * Returns a list of all notification categories and whether the user has them enabled.
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $result = $this->authService->getNotificationPreferences($request->user());

        return $this->successResponse($result, 'Notification preferences fetched successfully');
    }

    /**
     * Update notification preferences.
     *
     * Update enablement status for one or more notification categories.
     * Send an array of objects: `[{"category": "booking_updates", "is_enabled": true}, ...]`
     */
    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        // Convert array of objects to associative array for the service
        $preferences = collect($request->validated('preferences'))
            ->pluck('is_enabled', 'category')
            ->toArray();

        $this->authService->updateNotificationPreferences($request->user(), $preferences);

        return $this->successResponse(null, 'Notification preferences updated successfully');
    }
}
