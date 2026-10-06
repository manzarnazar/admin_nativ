<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService,
    ) {}

    /**
     * List all active currencies available for price display.
     */
    public function index(): JsonResponse
    {
        $currencies = $this->currencyService->getActive();

        return $this->successResponse([
            'items' => $currencies,
        ], 'Currencies fetched successfully');
    }
}
