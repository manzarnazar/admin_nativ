<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\NearbyPlaceCategory;
use App\Services\OpenStreetMapService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapsController extends Controller
{
    /**
     * Get nearby places for a city.
     *
     * Returns all nearby places stored for a given city, grouped by category.
     * Places are populated by `FetchCityNearbyPlacesJob` which calls the Overpass API (OSM mode)
     * or Google Places API (Google mode) and stores results in the database.
     * Check **Activity Logs** (`/activity-logs`) to see every fetch result — each log entry
     * includes `results_from_api` and `saved_to_db` counts.
     *
     * @tags Maps
     *
     * ## City IDs
     * | ID | City |
     * |----|------|
     * | 1 | Bhuj |
     * | 2 | Ahmedabad |
     * | 3 | Amreli |
     *
     * ## Category IDs
     * | ID | Category | OSM Type |
     * |----|----------|----------|
     * | 6 | Hospital | amenity=hospital |
     * | 7 | Temples around you | amenity=place_of_worship,religion=hindu |
     * | 8 | Places to eat | amenity=restaurant |
     * | 9 | Masjids nearby | amenity=place_of_worship,religion=muslim |
     * | 10 | Ice creams around you | amenity=ice_cream |
     * | 11 | Bus stations | amenity=bus_station |
     * | 12 | Bakery | shop=bakery |
     * | 13 | Medical shop | amenity=hospital |
     *
     * ### All places in Bhuj (city_id=1)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/nearby-places?city_id=1" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### All places in Ahmedabad (city_id=2)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/nearby-places?city_id=2" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Hospitals in Bhuj (city_id=1, category_id=6)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/nearby-places?city_id=1&category_id=6" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Restaurants in Ahmedabad (city_id=2, category_id=8)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/nearby-places?city_id=2&category_id=8" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Bakeries in Ahmedabad (city_id=2, category_id=12)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/nearby-places?city_id=2&category_id=12" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Medical shops in Bhuj (city_id=1, category_id=13) — likely 0 results, sparse OSM data
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/nearby-places?city_id=1&category_id=13" \
     *      -H "Accept: application/json"
     * ```
     */
    #[QueryParameter('city_id', description: '1=Bhuj · 2=Ahmedabad · 3=Amreli', required: true, type: 'integer', example: 1)]
    #[QueryParameter('category_id', description: '6=Hospital · 7=Temples · 8=Restaurants · 9=Masjids · 10=Ice Cream · 11=Bus Stations · 12=Bakery · 13=Medical Shop', required: false, type: 'integer', example: 6)]
    public function nearbyPlaces(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'category_id' => ['nullable', 'integer', 'exists:nearby_place_categories,id'],
        ]);

        $city = City::query()->findOrFail($request->integer('city_id'));

        $places = $city->nearbyPlaces()
            ->with('nearbyPlaceCategory')
            ->when($request->filled('category_id'), fn ($q) => $q->where('nearby_place_category_id', $request->integer('category_id')))
            ->get()
            ->groupBy('nearby_place_category_id')
            ->map(function ($group) {
                $category = $group->first()->nearbyPlaceCategory;

                return [
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'osm_place_type' => $category->osm_place_type,
                        'icon' => $category->icon ? asset('storage/'.$category->icon) : null,
                    ],
                    'places' => $group->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'address' => $p->address,
                        'latitude' => $p->latitude,
                        'longitude' => $p->longitude,
                        'rating' => $p->rating,
                    ])->values(),
                ];
            })->values();

        return $this->successResponse([
            'city' => ['id' => $city->id, 'name' => $city->name],
            'categories' => $places,
        ], 'Nearby places fetched successfully');
    }

    /**
     * List nearby place categories.
     *
     * Returns all active nearby place categories with their OSM / Google place types and radius.
     * Use `osm_place_type` as the tag in Overpass queries when debugging.
     *
     * @tags Maps
     *
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/categories" \
     *      -H "Accept: application/json"
     * ```
     */
    public function categories(): JsonResponse
    {
        $categories = NearbyPlaceCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'osm_place_type', 'google_place_type', 'radius', 'total_places', 'icon']);

        return $this->successResponse($categories, 'Categories fetched successfully');
    }

    /**
     * OSM Overpass API — debug nearby place fetch.
     *
     * Calls the Overpass API with the **exact same query** the background job uses.
     * Returns raw results immediately — no job, no queue wait.
     * If `results_count` is 0, OSM has no data for that tag near that city (sparse coverage, not a bug).
     *
     * @tags Maps
     *
     * ## City IDs
     * | ID | City | Latitude | Longitude |
     * |----|------|----------|-----------|
     * | 1 | Bhuj | 23.25397 | 69.66928 |
     * | 2 | Ahmedabad | 23.02579 | 72.58727 |
     * | 3 | Amreli | 21.50789 | 71.18323 |
     *
     * ## Category IDs
     * | ID | Category | OSM Type | Radius |
     * |----|----------|----------|--------|
     * | 6 | Hospital | amenity=hospital | 20km |
     * | 7 | Temples around you | amenity=place_of_worship,religion=hindu | 50km |
     * | 8 | Places to eat | amenity=restaurant | 5km |
     * | 9 | Masjids nearby | amenity=place_of_worship,religion=muslim | 50km |
     * | 10 | Ice creams around you | amenity=ice_cream | 10km |
     * | 11 | Bus stations | amenity=bus_station | 5km |
     * | 12 | Bakery | shop=bakery | 5km |
     * | 13 | Medical shop | amenity=hospital | 6km |
     *
     * ### Hospitals in Bhuj (city_id=1, category_id=6)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/debug/overpass?city_id=1&category_id=6" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Temples in Ahmedabad (city_id=2, category_id=7)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/debug/overpass?city_id=2&category_id=7" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Restaurants in Bhuj (city_id=1, category_id=8)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/debug/overpass?city_id=1&category_id=8" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Bakeries in Ahmedabad (city_id=2, category_id=12)
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/debug/overpass?city_id=2&category_id=12" \
     *      -H "Accept: application/json"
     * ```
     *
     * ### Medical shops in Bhuj (city_id=1, category_id=13) — 0 results expected, sparse OSM data
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/maps/debug/overpass?city_id=1&category_id=13" \
     *      -H "Accept: application/json"
     * ```
     *
     *
     * ---
     *
     * ## Raw Overpass curls — copy & paste to test directly
     *
     * ### Primary endpoint — hospitals near Bhuj (city_id=1, lat=23.25397, lng=69.66928)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="hospital"](around:20000,23.25397,69.66928);way["amenity"="hospital"](around:20000,23.25397,69.66928););out center 10;'
     * ```
     *
     * ### Primary endpoint — restaurants near Bhuj (amenity=restaurant, radius 5km)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="restaurant"](around:5000,23.25397,69.66928);way["amenity"="restaurant"](around:5000,23.25397,69.66928););out center 10;'
     * ```
     *
     * ### Primary endpoint — Hindu temples near Ahmedabad (city_id=2, lat=23.02579, lng=72.58727)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="place_of_worship"]["religion"="hindu"](around:50000,23.02579,72.58727);way["amenity"="place_of_worship"]["religion"="hindu"](around:50000,23.02579,72.58727););out center 10;'
     * ```
     *
     * ### Primary endpoint — mosques near Ahmedabad (amenity=place_of_worship, religion=muslim)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="place_of_worship"]["religion"="muslim"](around:50000,23.02579,72.58727);way["amenity"="place_of_worship"]["religion"="muslim"](around:50000,23.02579,72.58727););out center 10;'
     * ```
     *
     * ### Primary endpoint — bakeries near Ahmedabad (shop=bakery, radius 5km)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["shop"="bakery"](around:5000,23.02579,72.58727);way["shop"="bakery"](around:5000,23.02579,72.58727););out center 10;'
     * ```
     *
     * ### Primary endpoint — ice cream shops near Ahmedabad (amenity=ice_cream, radius 10km)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="ice_cream"](around:10000,23.02579,72.58727);way["amenity"="ice_cream"](around:10000,23.02579,72.58727););out center 10;'
     * ```
     *
     * ### Primary endpoint — bus stations near Bhuj (amenity=bus_station, radius 5km)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="bus_station"](around:5000,23.25397,69.66928);way["amenity"="bus_station"](around:5000,23.25397,69.66928););out center 10;'
     * ```
     *
     * ### Primary endpoint — medical shops near Bhuj (shop=chemist — sparse in India)
     * ```bash
     * curl -G "https://overpass-api.de/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["shop"="chemist"](around:6000,23.25397,69.66928);way["shop"="chemist"](around:6000,23.25397,69.66928););out center 10;'
     * ```
     *
     * ---
     *
     * ## Fallback mirrors — use when primary (overpass-api.de) is down
     *
     * ### kumi.systems — hospitals near Bhuj
     * ```bash
     * curl -G "https://overpass.kumi.systems/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="hospital"](around:20000,23.25397,69.66928);way["amenity"="hospital"](around:20000,23.25397,69.66928););out center 5;'
     * ```
     *
     * ### private.coffee — hospitals near Bhuj
     * ```bash
     * curl -G "https://overpass.private.coffee/api/interpreter" \
     *   --data-urlencode 'data=[out:json][timeout:15];(node["amenity"="hospital"](around:20000,23.25397,69.66928);way["amenity"="hospital"](around:20000,23.25397,69.66928););out center 5;'
     * ```
     *
     * ---
     *
     * ## Photon — property address autocomplete
     *
     * ### Search "bhuj" with India filter and location bias
     * ```bash
     * curl "https://photon.komoot.io/api?q=bhuj&limit=7&lang=en&countrycode=in&lat=23.25397&lon=69.66928&location_bias_scale=0.5"
     * ```
     *
     * ### Partial word test — "bhu" (should return results; Nominatim returns nothing for partial words)
     * ```bash
     * curl "https://photon.komoot.io/api?q=bhu&limit=5&lang=en&countrycode=in"
     * ```
     *
     * ### Search with Ahmedabad bias
     * ```bash
     * curl "https://photon.komoot.io/api?q=hotel&limit=7&lang=en&countrycode=in&lat=23.02579&lon=72.58727&location_bias_scale=0.5"
     * ```
     *
     * ---
     *
     * ## Nominatim — city management modal search
     *
     * ### Search for a place by name (used in city management "Add Nearby Place" modal)
     * ```bash
     * curl "https://nominatim.openstreetmap.org/search?q=hospital+bhuj&format=json&addressdetails=1&limit=5&countrycodes=in" \
     *   -H "User-Agent: TestfilaApp/1.0"
     * ```
     *
     * ### Reverse geocode — coords to address
     * ```bash
     * curl "https://nominatim.openstreetmap.org/reverse?lat=23.25397&lon=69.66928&format=json" \
     *   -H "User-Agent: TestfilaApp/1.0"
     * ```
     *
     * ### Reverse geocode — Ahmedabad center
     * ```bash
     * curl "https://nominatim.openstreetmap.org/reverse?lat=23.02579&lon=72.58727&format=json" \
     *   -H "User-Agent: TestfilaApp/1.0"
     * ```
     */
    #[QueryParameter('city_id', description: '1=Bhuj · 2=Ahmedabad · 3=Amreli', required: true, type: 'integer', example: 1)]
    #[QueryParameter('category_id', description: '6=Hospital · 7=Temples · 8=Restaurants · 9=Masjids · 10=Ice Cream · 11=Bus Stations · 12=Bakery · 13=Medical Shop', required: true, type: 'integer', example: 6)]
    public function debugOverpass(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'category_id' => ['required', 'integer', 'exists:nearby_place_categories,id'],
        ]);

        $city = City::query()->findOrFail($request->integer('city_id'));
        $category = NearbyPlaceCategory::query()->findOrFail($request->integer('category_id'));

        $debug = app(OpenStreetMapService::class)->debugSearchNearby(
            lat: (float) $city->latitude,
            lng: (float) $city->longitude,
            osmType: $category->osm_place_type,
            radiusMeters: $category->radius * 1000,
            maxResults: $category->total_places,
        );

        return $this->successResponse([
            'city' => ['id' => $city->id, 'name' => $city->name, 'lat' => $city->latitude, 'lng' => $city->longitude],
            'category' => ['id' => $category->id, 'name' => $category->name, 'osm_place_type' => $category->osm_place_type, 'radius_km' => $category->radius],
            'overpass_query' => $debug['query'],
            'endpoints_tried' => $debug['endpoints'],
            'results_count' => $debug['results_count'],
            'results' => $debug['results'],
        ], 'Overpass debug completed');
    }
}
