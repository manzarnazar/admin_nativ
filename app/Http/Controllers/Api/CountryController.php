<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\CountryService;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    public function __construct(
        private CountryService $countryService,
    ) {}

    /**
     * Get countries.
     *
     * Returns all active countries. The default country is listed first.
     */
    public function index(): JsonResponse
    {
        $result = $this->countryService->getCountries();

        return $this->successResponse($result, 'Countries fetched successfully');
    }
}
