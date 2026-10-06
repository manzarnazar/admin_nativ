<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(
        private OfferService $offerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:coupon,referral'],
            'scope' => ['nullable', 'string', 'in:active,inactive'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'property_slug' => ['nullable', 'string', 'exists:properties,slug'],
        ]);

        $result = $this->offerService->getOffers($request->user(), $request->all());

        return $this->successResponse($result, 'Offers fetched successfully');
    }
}
