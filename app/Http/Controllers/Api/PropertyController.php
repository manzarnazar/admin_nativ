<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnswerType;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyRuleQuestion;
use App\Services\Api\PropertyService;
use App\Services\GooglePlacesService;
use App\Services\RecentlyViewedService;
use App\Support\SystemMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct(
        private PropertyService $propertyService,
        private RecentlyViewedService $recentlyViewedService,
    ) {}

    /**
     * List properties.
     *
     * Returns active properties with filters. Response includes `min_price` and `max_price` across all matching properties (for price slider), plus `facilities` and `property_types` — the collective, deduplicated set of facilities/property types found across all matching properties (before pagination), for building filter UIs.
     *
     * **Core filters:**
     * - `search` — searches across property name, slug, street address, city name, and state name
     * - `property_type_id` — filter by property type (Hotel, Villa, etc.). Comma-separated for multiple (e.g. `2,5`).
     * - `pay_at_property` — `1` for pay at property only, `0` for prepaid only
     * - `pets` — `1` for pet-friendly properties only, `0` for properties that don't allow pets
     * - `ratings` — comma-separated star buckets 1-5 (e.g. `5,4`). A property's average
     *   rating is floored to a whole star and must match one of the given buckets (so a
     *   4.9-average property only matches `ratings=4`, not `ratings=5`). Properties with
     *   no reviews yet never match.
     * - `amenities` — comma-separated facility IDs or names (e.g., `1,wifi`).
     * - `min_price` / `max_price` — price range filter (based on room prices)
     * - `adults` + `children` — guest capacity filter
     * - `check_in` + `check_out` — date availability filter (YYYY-MM-DD)
     * - `rooms` — minimum number of rooms needed for the stay; only applied when `check_in`+`check_out` are also given. Checks combined inventory across all room types plus a greedy per-room-type guest-capacity check (fills the requested rooms starting from the highest-`max_guests` room type). Each returned property includes `single_room_type_available` (bool) — true when one room type alone covers the request (no split booking needed); such properties sort first.
     * - `sort_by` — options: `highly_rated`, `newest`, `price_low_high`, `price_high_low`
     * - `lat` + `lng` — sort by proximity to this point (e.g. after selecting a search-suggestion location); overridden by `sort_by` when both are given
     * - `radius_km` — hard-filter to properties within this distance of `lat`/`lng`; requires `lat`+`lng`, applies regardless of `sort_by`
     * - `limit` and `offset` — pagination. Default: limit=10, offset=0
     * - `rules` — dynamic property rule filters: a **JSON-encoded object string**, keyed by `question_id` (get the list from `GET /properties/rule-filters`), value(s) copied exactly from that question's `pill_labels` entries — the pill's `value` for Select types, the pill string itself for Yes/No. One selection is a string, Multi Select selections are an array — e.g. `rules={"15":"Yes","16":["a1b2c3d4","x9y8z7w6"]}` (URL-encode the JSON string when building the request). A property matches if it has ANY of the given values for a question, and must match every `question_id` given (AND across questions).
     *
     * **Homepage section passthrough — `city_slug` / `country_id`:**
     * These two exist specifically so a `GET /homepage/sections` section's "Show All"
     * can be implemented by calling this endpoint directly, instead of a separate
     * per-section properties endpoint:
     * - `country_id` — pass a section's `target_country_id` straight through
     * - `city_slug` — pass a section's `target_city.slug` straight through. **Not**
     *   `target_city.id` — that's the internal city record ID, not a filter value.
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/properties?sort_by=highly_rated&limit=10&offset=0" \
     *      -H "Accept: application/json"
     * ```
     *
     * **Accept-Currency header** — converts `starting_price` to the specified currency
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer'],
            'property_type_id' => ['nullable', 'regex:/^\d+(,\d+)*$/'],
            'city_slug' => ['nullable', 'string', 'max:255'],
            'pay_at_property' => ['nullable', 'boolean'],
            'pets' => ['nullable', 'boolean'],
            'ratings' => ['nullable', 'regex:/^[1-5](,[1-5])*$/'],
            'amenities' => ['nullable', 'string'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'adults' => ['nullable', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'rooms' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort_by' => ['nullable', 'string', 'in:highly_rated,newest,price_low_high,price_high_low'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'rules' => ['nullable', 'json'],
        ]);

        $filters = $request->all();

        if (! empty($filters['rules'])) {
            $filters['rules'] = json_decode((string) $filters['rules'], true) ?? [];
        }

        $result = $this->propertyService->getProperties($filters, SystemMode::isMulti() ? $request->user('sanctum')?->id : null);

        $paginator = $result['paginator'];

        return $this->successResponse([
            'min_price' => $result['min_price'],
            'max_price' => $result['max_price'],
            'currency_code' => $result['currency_code'] ?? null,
            'currency_symbol' => $result['currency_symbol'] ?? null,
            'converted_min_price' => $result['converted_min_price'] ?? null,
            'converted_max_price' => $result['converted_max_price'] ?? null,
            'converted_currency_code' => $result['converted_currency_code'] ?? null,
            'converted_currency_symbol' => $result['converted_currency_symbol'] ?? null,
            'exchange_rate' => $result['exchange_rate'] ?? null,
            'facilities' => $result['facilities'] ?? [],
            'property_types' => $result['property_types'] ?? [],
            'items' => $result['items'],
            'pagination' => [
                'total' => $paginator->total(),
                'limit' => $paginator->perPage(),
                'offset' => ($paginator->currentPage() - 1) * $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'Properties fetched successfully');
    }

    /**
     * property list for dropdowns.
     *
     * Lightweight property list for dropdowns.
     *
     * Returns only the minimum fields needed for search/select UIs.
     *
     * **Optional filter:**
     * - `country_id` — restricts results to properties in the given country. Unknown or inactive country IDs are silently ignored (all properties returned).
     */
    public function dropdown(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
        ]);

        $result = $this->propertyService->getPropertyDropdownItems($request->only([
            'search',
            'country_id',
            'limit',
            'offset',
            'lat',
            'lng',
            'radius_km',
        ]));

        return $this->successResponse($result, 'Property dropdown fetched successfully');
    }

    /**
     * Get property rule filters.
     *
     * Returns active property rules to be used as dynamic filters in the frontend.
     * `pill_labels` shape depends on `type`:
     * - `yes_no` — a plain array of two strings, `["Yes", "No"]`. These are fixed
     *   and never renamed, so the string itself doubles as the value to submit.
     * - `single_select` / `multiple_select` — an array of `{value, label}`. Always
     *   display `label` and submit `value` back — `value` is a stable option ID
     *   that survives the admin renaming the option's text later, so don't treat
     *   it as display text.
     * Build a `{question_id: value(s)}` object from every selected pill across all
     * rules and send it as one JSON-encoded string in the `rules` filter on
     * `GET /properties` (see that endpoint's docs for the exact format).
     *
     * **Suggested rendering** — group by `type`: `yes_no` rules are a single
     * standalone toggle pill (pill text = `filter_label`, e.g. "Smoking Allowed").
     * `single_select` / `multiple_select` rules have more than one `pill_labels`
     * entry, so they need their own section with `filter_label` as a visible
     * heading — they can't be flattened into a single unlabeled pill row without
     * losing which options belong to which question.
     *
     * **Optional scope filters** — same convention as `GET /properties`. Rules
     * created without a country/property type apply everywhere and are always
     * included; omitting these params returns only those universal rules.
     * - `country_id` — only rules that apply to this country
     * - `property_type_id` — only rules that apply to this property type. Comma-separated for multiple (e.g. `2,5`).
     */
    public function ruleFilters(Request $request): JsonResponse
    {
        $request->validate([
            'country_id' => ['nullable', 'integer'],
            'property_type_id' => ['nullable', 'regex:/^\d+(,\d+)*$/'],
        ]);

        $propertyTypeIds = $request->filled('property_type_id')
            ? array_map('intval', explode(',', (string) $request->query('property_type_id')))
            : null;

        $questions = PropertyRuleQuestion::query()
            ->whereHas('propertyRule', function ($query) use ($request, $propertyTypeIds) {
                $query->where('status', 'active')
                    ->applicableTo(
                        $request->filled('country_id') ? (int) $request->query('country_id') : null,
                        $propertyTypeIds,
                    );
            })
            ->orderBy('sort_order')
            ->get()
            ->map(function (PropertyRuleQuestion $question) {
                return [
                    'question_id' => $question->id,
                    'type' => $question->answer_type->value,
                    'filter_label' => $question->filter_label,
                    'pill_labels' => $question->answer_type === AnswerType::YesNo
                        ? ['Yes', 'No']
                        : $question->normalizedOptions()
                            ->map(fn (array $opt) => ['value' => $opt['id'], 'label' => $opt['label']])
                            ->all(),
                ];
            });

        return $this->successResponse(['rules' => $questions], 'Property rule filters fetched successfully');
    }

    /**
     * Property details.
     *
     * Returns full details of a property including facilities, rules, and images.
     * Rooms are fetched separately via `GET /properties/rooms`.
     * Only active properties are returned.
     *
     * **Query Parameters:**
     * - `slug` (required) — property slug
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/properties/show?slug=my-awesome-hotel" \
     *      -H "Accept: application/json"
     * ```
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $user = SystemMode::isMulti() ? $request->user('sanctum') : null;
        $result = $this->propertyService->getPropertyDetails($request->slug, $user?->id);

        if ($user) {
            $this->recentlyViewedService->recordView($user->id, $result['id']);
        }

        return $this->successResponse($result, 'Property fetched successfully');
    }

    /**
     * Get property details by slug (route parameter) - LEGACY.
     *
     * @deprecated Use /properties/show?slug={slug} instead
     * This route is kept for backward compatibility and will be removed in a future version.
     */
    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        $user = SystemMode::isMulti() ? $request->user('sanctum') : null;
        $result = $this->propertyService->getPropertyDetails($slug, $user?->id);

        if ($user) {
            $this->recentlyViewedService->recordView($user->id, $result['id']);
        }

        return $this->successResponse($result, 'Property fetched successfully');
    }

    /**
     * Property rooms.
     *
     * Returns rooms for a property, including room type details, images, and facilities.
     * All filters are optional — without them, all rooms are returned.
     *
     * - If `property_slug` is not provided and there is only one property in the system, its rooms are returned automatically.
     * - If `property_slug` is not provided and there are multiple properties, an error is returned.
     *
     * **Filters:**
     *
     * - `check_in` + `check_out` (date, format: YYYY-MM-DD) — Only rooms available for those dates are returned. Response includes `available_rooms`, `nights`, and `total_price`. Example: `?check_in=2026-04-07&check_out=2026-04-10` → only rooms with inventory for all 3 nights.
     *
     * - `adults` + `children` (integer) — Filters by guest capacity. Only rooms where `max_guests >= adults + children` are shown. Example: `?adults=2&children=1` → only rooms that fit 3 guests.
     *
     * - `rooms` (integer, default: 1) — Minimum number of rooms needed. Rooms with fewer available are hidden. Example: `?rooms=3` → if a room type has only 2 available, it won't show up.
     *
     * - `amenities` (string) — comma-separated facility IDs or names (e.g., `1,wifi`).
     *
     * - `min_price` / `max_price` (numeric) — price range filter.
     *
     * - `sort_by` (string) — options: `highly_rated`, `price_low_high`, `price_high_low`.
     *
     * **Combined example:** `?check_in=2026-04-07&check_out=2026-04-10&adults=2&children=1&rooms=2` → rooms that fit 3 guests, have at least 2 available for those 3 nights.
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/properties/rooms?property_slug=hotel-paradise-bhuj&sort_by=price_low_high&limit=10" \
     *      -H "Accept: application/json"
     * ```
     *
     * **Custom Headers:**
     *
     * - `Accept-Currency: USD` — converts prices to the specified currency. Response adds `converted_base_price_per_night`, `converted_currency_code`, `converted_currency_symbol`, `exchange_rate`. If not sent, only base prices are returned.
     *
     * - `Accept-Language: en` — returns content in the specified language (e.g., `en`, `ar`). Default: `en`.
     *
     * **Example curl with headers:**
     *
     * ```bash
     * curl -H "Accept-Currency: USD" -H "Accept-Language: en" -X GET "https://dev-estay.thewrteam.in/api/properties/rooms?property_slug=hotel-paradise-bhuj"
     * ```
     */
    public function rooms(Request $request): JsonResponse
    {
        $request->validate([
            'property_slug' => ['nullable', 'string'],
            'room_slug' => ['nullable', 'string'],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rooms' => ['nullable', 'integer', 'min:1'],
            'amenities' => ['nullable', 'string'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'in:1,2,3,4,5'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort_by' => ['nullable', 'string', 'in:highly_rated,price_low_high,price_high_low'],
        ]);

        $roomSlug = $request->input('room_slug');
        $propertySlug = $request->input('property_slug');

        if ($roomSlug && ! $propertySlug) {
            $propertyId = $this->propertyService->resolvePropertyIdFromRoomSlug($roomSlug);
        } else {
            $propertyId = $this->propertyService->resolvePropertyId($propertySlug);
        }

        $result = $this->propertyService->getPropertyRooms($propertyId, $request->only([
            'room_slug',
            'check_in',
            'check_out',
            'adults',
            'children',
            'rooms',
            'amenities',
            'min_price',
            'max_price',
            'rate',
            'limit',
            'offset',
            'sort_by',
        ]));

        return $this->successResponse($result, 'Rooms fetched successfully');
    }

    /**
     * Get nearby places for a property.
     *
     * Returns nearby places added to the city of the property, grouped by category.
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/properties/hotel-paradise-bhuj/nearby-places" \
     *      -H "Accept: application/json"
     * ```
     */
    public function nearbyPlaces(string $slug): JsonResponse
    {
        $property = Property::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->with('city.nearbyPlaces.nearbyPlaceCategory')
            ->firstOrFail();

        if (! $property->city) {
            return $this->successResponse([], 'No city found for this property');
        }

        $propertyLat = (float) $property->latitude;
        $propertyLng = (float) $property->longitude;

        $nearbyPlaces = $property->city->nearbyPlaces()
            ->with('nearbyPlaceCategory')
            ->get()
            ->groupBy('nearby_place_category_id')
            ->map(function ($places) use ($propertyLat, $propertyLng) {
                $category = $places->first()->nearbyPlaceCategory;

                return [
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'icon' => $category->icon ? asset('storage/'.$category->icon) : null,
                    ],
                    'places' => $places->map(function ($place) use ($propertyLat, $propertyLng) {
                        return [
                            'id' => $place->id,
                            'name' => $place->name,
                            'google_place_id' => $place->google_place_id,
                            'latitude' => $place->latitude,
                            'longitude' => $place->longitude,
                            'address' => $place->address,
                            'distance_km' => app(GooglePlacesService::class)->calculateDistance(
                                $propertyLat,
                                $propertyLng,
                                (float) $place->latitude,
                                (float) $place->longitude,
                            ),
                            'rating' => $place->rating,
                        ];
                    })->values(),
                ];
            })->values();

        return $this->successResponse($nearbyPlaces, 'Nearby places fetched successfully');
    }
}
