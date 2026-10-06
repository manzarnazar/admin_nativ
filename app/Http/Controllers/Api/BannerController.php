<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\BannerService;
use App\Services\Api\IpLocationService;
use App\Services\HomepagePreviewOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(
        private BannerService $bannerService,
        private IpLocationService $ipLocationService,
        private HomepagePreviewOverrideService $homepagePreviewOverrideService,
    ) {}

    /**
     * Get banners for a country.
     *
     * If `country_id` is provided, banners for that country are returned. Otherwise
     * `latitude`/`longitude` (browser geolocation, resolved to the nearest active city
     * we operate in) is used if given. If both are omitted, the country is resolved
     * from the request IP. When the country cannot be resolved, or has no banners,
     * global banners are returned as the fallback.
     *
     * Same admin Live Preview override as HomepageController::sections() — see
     * App\Services\HomepagePreviewOverrideService for how this is populated.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $override = $this->homepagePreviewOverrideService->resolve($request->ip());

        $countryId = match (true) {
            $override !== null => $override['force_global'] ? null : $override['country_id'],
            $request->filled('country_id') => $request->integer('country_id'),
            $request->filled(['latitude', 'longitude']) => $this->ipLocationService->getCountryFromCoordinates(
                $request->float('latitude'),
                $request->float('longitude'),
            )?->id,
            default => $this->ipLocationService->getCountryFromIp()?->id,
        };

        $banners = $this->bannerService->getBanners($countryId);

        return $this->successResponse($banners, 'Banners fetched successfully');
    }
}
