<?php

namespace App\Http\Controllers\PartnerApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerApi\Auth\RegisterRequest;
use App\Services\PartnerApi\PartnerAuthService;
use Illuminate\Http\JsonResponse;

class PartnerAuthController extends Controller
{
    public function __construct(
        private PartnerAuthService $authService,
    ) {}

    /**
     * Register partner account.
     *
     * Create a partner account after email OTP or Firebase phone verification.
     *
     * **Email OTP flow:**
     * 1. Call `POST /api/auth/send-email-otp` with `purpose: partner_registration`.
     * 2. Call `POST /api/auth/otp/verify` to get a `verification_token`.
     * 3. Call this endpoint with `email`, `verification_token`, and personal details.
     *
     * **Phone OTP flow:**
     * 1. Frontend verifies phone via Firebase.
     * 2. Call this endpoint with `phone`, `dial_code`, `firebase_id_token`, and personal details.
     *
     * @tags Partner Auth
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->successResponse($result, 'Registration successful.', 201);
    }
}
