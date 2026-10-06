<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Validate a coupon code.
     *
     * Check if a coupon is valid for the authenticated user.
     */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'booking_amount' => ['nullable', 'numeric'],
        ]);

        $coupon = $this->couponService->validateCoupon(
            $request->code,
            $request->user(),
            (float) ($request->booking_amount ?? 0)
        );

        return response()->json([
            'error' => false,
            'message' => 'Coupon is valid',
            'data' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'expires_at' => $coupon->expires_at?->toISOString(),
            ],
        ]);
    }
}
