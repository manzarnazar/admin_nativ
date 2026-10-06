<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService,
    ) {}

    /**
     * Combined search suggestions.
     *
     * Returns two sections: `locations` (matching cities/places — our own
     * cities with real inventory first, then general places from OSM) and
     * `properties` (matching property name, slug, or street address).
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay-multi.thewrteam.in/api/search/suggest?q=mu" \
     *      -H "Accept: application/json"
     * ```
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'country_id' => ['nullable', 'integer'],
        ]);

        $countryId = $request->integer('country_id') ?: null;
        $lat = null;
        $lng = null;
        $userCountry = null;

        if (! $countryId) {
            try {
                if ($ip = request()->ip()) {
                    if ($position = \Stevebauman\Location\Facades\Location::get($ip)) {
                        $lat = $position->latitude ? (float) $position->latitude : null;
                        $lng = $position->longitude ? (float) $position->longitude : null;
                        
                        if ($position->countryCode) {
                            $userCountry = \App\Models\Country::where('iso_code', $position->countryCode)->value('name');
                        }
                    }
                }
            } catch (\Exception) {
                // Safely ignore geolocation failure
            }
        }

        $result = $this->searchService->suggest(
            $request->string('q')->toString(),
            $countryId,
            $lat,
            $lng,
            $userCountry,
        );

        return $this->successResponse($result, 'Search results fetched successfully');
    }
}
