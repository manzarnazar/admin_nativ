<?php

namespace App\Services\Api;

use App\Actions\CalculateRoomAvailabilityAction;
use App\Enums\InventoryLockStatus;
use App\Enums\ReviewStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\Facility;
use App\Models\Favorite;
use App\Models\InventoryLock;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\Review;
use App\Models\RoomInventory;
use App\Models\Setting;
use App\Services\CancellationPolicyService;
use App\Support\Geo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Validation\ValidationException;
use Stevebauman\Location\Facades\Location;

class PropertyService
{
    /**
     * Get the user's lat/lng from their IP address.
     * Returns null when geolocation is unavailable or the property has no coordinates.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function getLocationFromIp(): ?array
    {
        try {
            $ip = request()->ip();
            $cacheKey = "ip_location:{$ip}";

            return cache()->remember($cacheKey, now()->addHours(24), function () use ($ip) {
                $position = Location::get($ip);

                if (! $position || ! $position->latitude || ! $position->longitude) {
                    return null;
                }

                return [
                    'lat' => (float) $position->latitude,
                    'lng' => (float) $position->longitude,
                ];
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build Haversine distance expression (km) for the given coordinates.
     */
    private function haversineExpression(float $lat, float $lng): Expression
    {
        return Geo::haversineExpression($lat, $lng);
    }

    /**
     * Get lightweight property list for dropdown/search components.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPropertyDropdownItems(array $filters = []): array
    {
        $limit = $filters['limit'] ?? 20;
        $offset = $filters['offset'] ?? 0;
        $page = (int) floor($offset / $limit) + 1;

        $query = Property::query()
            ->publiclyVisible()
            ->with([
                'refCity:id,name',
                'refState:id,name',
                'country:id,name',
            ]);

        if (! empty($filters['country_id'])) {
            $countryExists = Country::whereKey($filters['country_id'])
                ->where('is_active', true)
                ->exists();

            if ($countryExists) {
                $query->where('country_id', $filters['country_id']);
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('refCity', fn ($subQ) => $subQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('refState', fn ($subQ) => $subQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('country', fn ($subQ) => $subQ->where('name', 'like', "%{$search}%"));
            });
        }

        $explicitLat = isset($filters['lat']) ? (float) $filters['lat'] : null;
        $explicitLng = isset($filters['lng']) ? (float) $filters['lng'] : null;
        $radiusKm = isset($filters['radius_km']) ? (float) $filters['radius_km'] : (float) Setting::get('default_search_radius_km', 50);

        $userLocation = ($explicitLat === null) ? $this->getLocationFromIp() : null;

        if ($explicitLat !== null && $explicitLng !== null) {
            $query->whereRaw(Geo::haversineFormula($explicitLat, $explicitLng).' <= ?', [$radiusKm]);
            $query->select('properties.*')
                ->addSelect($this->haversineExpression($explicitLat, $explicitLng))
                ->orderByRaw('CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END')
                ->orderBy('distance_km');
        } elseif ($userLocation) {
            ['lat' => $lat, 'lng' => $lng] = $userLocation;
            $query->select('properties.*')
                ->addSelect($this->haversineExpression($lat, $lng))
                ->orderByRaw('CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END')
                ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        $paginator = $query->paginate(perPage: $limit, page: $page);

        return [
            'items' => $paginator->getCollection()->map(fn (Property $property) => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'city' => $property->refCity?->name,
                'state' => $property->refState?->name,
                'country' => $property->country?->name,
                'country_id' => $property->country?->id,
                'distance_km' => isset($property->distance_km) ? round((float) $property->distance_km, 2) : null,
            ])->values()->toArray(),
            'pagination' => [
                'total' => $paginator->total(),
                'limit' => $paginator->perPage(),
                'offset' => ($paginator->currentPage() - 1) * $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * SQL fragment computing a property's conversion factor into the request's
     * display currency: 1.0 when the property currency already equals the target,
     * the stored exchange rate otherwise, or NULL when no rate exists. Requires two
     * positional bindings ($targetCurrency twice) each time it is embedded.
     */
    private function currencyFactorSql(string $propertyIdColumn): string
    {
        return "(SELECT CASE WHEN cc.currency_code = ? THEN 1 ELSE er.rate END
            FROM properties pp
            JOIN countries cc ON cc.id = pp.country_id
            LEFT JOIN exchange_rates er
                ON er.base_currency = cc.currency_code AND er.target_currency = ?
            WHERE pp.id = {$propertyIdColumn}
            LIMIT 1)";
    }

    /**
     * Bayesian-weighted "highly rated" sort — the single ranking formula behind
     * every "highly rated"/"top rated" property listing in the app (property
     * search and homepage sections alike), so a property's rank is never a
     * different figure depending on which endpoint asked for it.
     *
     * Requires `reviews_avg_rating`/`reviews_count` to already be selected on
     * the query (via `withAvg('reviews', 'rating')->withCount('reviews')`).
     * Callers should add their own final tiebreaker after calling this.
     */
    public function applyHighlyRatedSort(Builder $query): Builder
    {
        // "Trust threshold": how many reviews before a property's own average dominates
        // the global mean. Tune this single number — smaller trusts few-review properties
        // sooner, larger requires more reviews to rank highly.
        $smoothing = 5;
        $globalAvgRating = (float) (Review::query()
            ->where('status', ReviewStatus::Published)
            ->where('is_visible', true)
            ->avg('rating') ?? 0);

        // Bayesian weighted rating: (v*R + m*C) / (v + m); no-review properties sort last
        return $query->orderByRaw(
            'CASE WHEN COALESCE(reviews_count, 0) = 0 THEN -1
                  ELSE (reviews_count * reviews_avg_rating + ? * ?) / (reviews_count + ?)
             END DESC',
            [$smoothing, $globalAvgRating, $smoothing]
        )->orderByDesc('reviews_count');
    }

    /**
     * Get property listing with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getProperties(array $filters = [], ?int $userId = null): array
    {
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        $page = (int) floor($offset / $limit) + 1;
        $sortBy = $filters['sort_by'] ?? null;

        // Explicit coordinates (e.g. from selecting a search-suggestion location) take
        // priority over IP-based geolocation.
        $explicitLat = isset($filters['lat']) ? (float) $filters['lat'] : null;
        $explicitLng = isset($filters['lng']) ? (float) $filters['lng'] : null;
        $radiusKm = isset($filters['radius_km']) ? (float) $filters['radius_km'] : (float) Setting::get('default_search_radius_km', 50);

        // Resolve user location early — only needed when no explicit sort/coordinates given
        $userLocation = ($sortBy === null && $explicitLat === null) ? $this->getLocationFromIp() : null;

        // Display currency for the request; all price comparisons/sorts/bounds are normalized to it
        $targetCurrency = CurrencyConverter::getTargetCurrency();
        $roomCurrencyFactor = $this->currencyFactorSql('property_rooms.property_id');

        $query = Property::query()
            ->publiclyVisible()
            ->with([
                'refState:id,name',
                'refCity:id,name',
                'primaryImages',
                'country:id,name,currency_code,currency_symbol',
                'facilities:id,name,icon',
            ]);

        // Calculate min price dynamically per property based on guest filter
        $totalGuests = ($filters['adults'] ?? 0) + ($filters['children'] ?? 0);

        // Search — name, slug, street address, city, state (each search word must
        // appear somewhere across these; catches village/locality names that only
        // ever exist in the free-text street_address, not a structured city field)
        if (! empty($filters['search'])) {
            $query->matchesSearchTerms($filters['search'], ['name', 'slug', 'street_address', 'refCity.name', 'refState.name']);
        }

        // Proximity radius filter — independent of sort order, so it still applies
        // even when the request also asks for sort_by=price_low_high etc.
        if ($explicitLat !== null && $explicitLng !== null) {
            $query->whereRaw(Geo::haversineFormula($explicitLat, $explicitLng).' <= ?', [$radiusKm]);
        }

        // Property type filter — accepts a single ID or a comma-separated list.
        if (! empty($filters['property_type_id'])) {
            $propertyTypeIds = array_map('intval', explode(',', (string) $filters['property_type_id']));
            $query->whereIn('property_type_id', $propertyTypeIds);
        }

        // City filter — same slug returned by /homepage/sections' target_city, so
        // a section's "Show All" can filter by it. Resolved to the underlying
        // ref_city_id that properties are actually stored against; an unknown
        // slug (or a city with no ref_city_id at all — the rare state-as-city
        // fallback) intentionally matches nothing rather than being ignored.
        if (! empty($filters['city_slug'])) {
            $refCityId = City::query()->where('slug', $filters['city_slug'])->value('ref_city_id');
            $query->where('ref_city_id', $refCityId ?? -1);
        }

        // Pay at property filter
        if (isset($filters['pay_at_property'])) {
            $query->where('pay_at_property', (bool) $filters['pay_at_property']);
        }

        // Pets allowed filter
        if (isset($filters['pets'])) {
            $query->where('pets_allowed', (bool) $filters['pets']);
        }

        // Room-count filter — only engages when `rooms` is given alongside a full
        // date range; otherwise falls back to the weaker existence-only checks
        // below, unchanged, for backward compatibility with existing callers.
        $roomsRequested = isset($filters['rooms']) ? (int) $filters['rooms'] : null;
        $hasDateRange = ! empty($filters['check_in']) && ! empty($filters['check_out']);
        $useStrictRoomAvailability = $roomsRequested !== null && $hasDateRange;

        if ($useStrictRoomAvailability) {
            // Precise bulk inventory + greedy capacity check across all room
            // types, computed in one set-based query (see
            // CalculateRoomAvailabilityAction) rather than per-property, so it
            // stays correct and fast across a paginated list.
            $candidateIds = (clone $query)->select('properties.id');

            $availability = app(CalculateRoomAvailabilityAction::class)->handle(
                $candidateIds,
                $filters['check_in'],
                $filters['check_out'],
                $roomsRequested,
            );

            $query->joinSub($availability, 'room_availability', 'room_availability.property_id', '=', 'properties.id')
                ->where('room_availability.total_available_rooms', '>=', $roomsRequested)
                ->where('room_availability.greedy_capacity', '>=', $totalGuests);
        } else {
            // Guest capacity filter — property must have at least one room fitting guests
            if ($totalGuests > 0) {
                $query->whereHas('rooms.roomType', fn ($q) => $q->where('max_guests', '>=', $totalGuests));
            }

            // Date availability filter — room_inventory rows are only created lazily, on the
            // first booking/lock attempt for a given room+date (see BookingInventoryService::
            // reserve()). A date with no row is fully available (matches BookingInventoryService::
            // getAvailableRooms()'s semantics), so a room type only fails this check when an
            // *existing* row for a date in range shows insufficient capacity — never merely for
            // lacking a row.
            if ($hasDateRange) {
                $checkIn = $filters['check_in'];
                $checkOut = $filters['check_out'];

                $query->whereHas('rooms', function ($q) use ($checkIn, $checkOut) {
                    $q->where('total_rooms', '>', 0)
                        ->whereDoesntHave('inventories', function ($q) use ($checkIn, $checkOut) {
                            $q->where('date', '>=', $checkIn)
                                ->where('date', '<', $checkOut)
                                ->whereRaw('total_rooms - booked_rooms - locked_rooms <= 0');
                        });
                });
            }
        }

        // --- COMPUTE BOUNDS BEFORE PRICE/AMENITIES FILTERS ---

        // Get global min/max price across all matching properties (before pagination),
        // normalized to the display currency and excluding soft-deleted rooms
        $priceRange = (clone $query)
            ->join('property_rooms', 'properties.id', '=', 'property_rooms.property_id')
            ->whereNull('property_rooms.deleted_at')
            ->selectRaw(
                "MIN(property_rooms.base_price_per_night * {$roomCurrencyFactor}) as min_price, ".
                    "MAX(property_rooms.base_price_per_night * {$roomCurrencyFactor}) as max_price",
                [$targetCurrency, $targetCurrency, $targetCurrency, $targetCurrency]
            )
            ->first();

        // Get all unique facilities available in the matching properties
        $matchedPropertyIds = (clone $query)->select('properties.id');
        $availableFacilities = Facility::query()
            ->whereHas('properties', function ($q) use ($matchedPropertyIds) {
                $q->whereIn('properties.id', $matchedPropertyIds);
            })
            ->with('category')
            ->get()
            ->map(fn ($fac) => [
                'id' => $fac->id,
                'name' => $fac->name,
                'icon' => $fac->getIconUrl(),
                'category' => $fac->category?->name,
            ])->toArray();

        // Get all unique property types available in the matching properties —
        // only active ones, since this is a customer-facing filter source, not
        // an admin listing. property_type_id is a direct column on properties
        // (unlike facilities, which go through a pivot), so this mirrors the
        // $matchedPropertyIds clone above rather than a whereHas.
        $matchedPropertyTypeIds = (clone $query)->select('properties.property_type_id')->distinct();
        $availablePropertyTypes = PropertyType::query()
            ->where('is_active', true)
            ->whereIn('id', $matchedPropertyTypeIds)
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'icon' => $type->icon_url,
            ])->toArray();

        // Select the joined flag now — must happen after the price-bounds and
        // facilities clones above, since selectRaw()/addSelect() on later
        // clones merge onto whatever columns are already set here, and those
        // two clones rely on $query->columns still being null/explicitly
        // reset at the point they're taken.
        if ($useStrictRoomAvailability) {
            $query->addSelect(['properties.*', 'room_availability.single_room_type_available']);
        }

        // --- APPLY AMENITIES AND PRICE FILTERS ---

        // Amenities filter — property must have ALL selected amenities
        if (! empty($filters['amenities'])) {
            $amenityIds = is_array($filters['amenities'])
                ? $filters['amenities']
                : explode(',', $filters['amenities']);

            foreach ($amenityIds as $amenityId) {
                $query->whereHas('facilities', function ($q) use ($amenityId) {
                    $q->where(function ($sub) use ($amenityId) {
                        $sub->where('facilities.id', $amenityId)
                            ->orWhere('facilities.name', 'like', "%{$amenityId}%");
                    });
                });
            }
        }

        // Property rules filter — a property must match at least one selected
        // option per rule (e.g. Gym OR Pool), and must satisfy every requested rule
        if (! empty($filters['rules']) && is_array($filters['rules'])) {
            foreach ($filters['rules'] as $questionId => $filterValue) {
                if (empty($filterValue)) {
                    continue;
                }

                $values = is_array($filterValue) ? $filterValue : [$filterValue];

                $query->whereHas('ruleAnswers', function ($q) use ($questionId, $values) {
                    $q->where('property_rule_question_id', $questionId)
                        ->where(function ($sub) use ($values) {
                            foreach ($values as $value) {
                                $sub->orWhereJsonContains('answer_value', $value);
                            }
                        });
                });
            }
        }

        // Price range filter — property must have at least one room whose
        // display-currency price falls within the requested range
        if (! empty($filters['min_price']) || ! empty($filters['max_price'])) {
            $query->whereHas('rooms', function ($q) use ($filters, $targetCurrency, $roomCurrencyFactor) {
                if (! empty($filters['min_price'])) {
                    $q->whereRaw("property_rooms.base_price_per_night * {$roomCurrencyFactor} >= ?", [$targetCurrency, $targetCurrency, $filters['min_price']]);
                }
                if (! empty($filters['max_price'])) {
                    $q->whereRaw("property_rooms.base_price_per_night * {$roomCurrencyFactor} <= ?", [$targetCurrency, $targetCurrency, $filters['max_price']]);
                }
            });
        }

        $query->withAvg(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)])
            ->withMin([
                'rooms' => function ($q) use ($totalGuests, $filters, $targetCurrency, $roomCurrencyFactor) {
                    if ($totalGuests > 0) {
                        $q->whereHas('roomType', fn ($qT) => $qT->where('max_guests', '>=', $totalGuests));
                    }
                    if (! empty($filters['min_price'])) {
                        $q->whereRaw("property_rooms.base_price_per_night * {$roomCurrencyFactor} >= ?", [$targetCurrency, $targetCurrency, $filters['min_price']]);
                    }
                    if (! empty($filters['max_price'])) {
                        $q->whereRaw("property_rooms.base_price_per_night * {$roomCurrencyFactor} <= ?", [$targetCurrency, $targetCurrency, $filters['max_price']]);
                    }
                },
            ], 'base_price_per_night');

        // Ratings filter — property's average rating, floored to a whole star, must
        // match at least one requested bucket (e.g. ratings=5,4 -> floor(avg) in (5,4)).
        // Filters directly against the same correlated subquery withAvg() above uses,
        // rather than the reviews_avg_rating select alias — MySQL allows referencing a
        // select alias in HAVING without GROUP BY, but SQLite (used in tests) doesn't,
        // so this stays portable across both. No-review properties (null average, since
        // FLOOR(NULL) is NULL and never matches an IN (...) list) never match.
        if (! empty($filters['ratings'])) {
            $ratingBuckets = array_map(
                'intval',
                is_array($filters['ratings']) ? $filters['ratings'] : explode(',', (string) $filters['ratings'])
            );

            $reviewAvgSubquery = Review::query()
                ->selectRaw('AVG(rating)')
                ->whereColumn('property_id', 'properties.id')
                ->where('status', ReviewStatus::Published)
                ->where('is_visible', true);

            $query->whereRaw(
                'FLOOR(('.$reviewAvgSubquery->toSql().')) IN ('.implode(',', array_fill(0, count($ratingBuckets), '?')).')',
                [...$reviewAvgSubquery->getBindings(), ...$ratingBuckets]
            );
        }

        if ($useStrictRoomAvailability) {
            // Properties that can fulfil the request from a single room type
            // (no split booking needed) sort before ones that only pass via
            // the combined check; existing sort_by still applies within tier.
            $query->orderByDesc('room_availability.single_room_type_available');
        }

        if ($sortBy === 'highly_rated') {
            $this->applyHighlyRatedSort($query)->orderBy('name');
        } elseif ($sortBy === 'newest') {
            $query->latest('id');
        } elseif ($sortBy === 'price_low_high' || $sortBy === 'price_high_low') {
            $direction = $sortBy === 'price_low_high' ? 'asc' : 'desc';
            $sortCurrencyFactor = $this->currencyFactorSql('properties.id');
            $query->orderByRaw("rooms_min_base_price_per_night * {$sortCurrencyFactor} {$direction}", [$targetCurrency, $targetCurrency])
                ->orderBy('name');
        } elseif ($explicitLat !== null && $explicitLng !== null) {
            $query->addSelect($this->haversineExpression($explicitLat, $explicitLng))
                ->orderByRaw('CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END')
                ->orderBy('distance_km');
        } elseif ($userLocation) {
            ['lat' => $lat, 'lng' => $lng] = $userLocation;
            $query->addSelect($this->haversineExpression($lat, $lng))
                ->orderByRaw('CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END')
                ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        $paginator = $query->paginate(perPage: $limit, page: $page);

        $favoritedIds = $userId
            ? Favorite::where('user_id', $userId)
                ->whereIn('property_id', $paginator->getCollection()->pluck('id'))
                ->pluck('property_id')
                ->all()
            : [];

        $items = $paginator->getCollection()->map(
            fn (Property $property) => $this->formatCompactProperty($property, in_array($property->id, $favoritedIds, true))
        )->toArray();

        // Bounds are already normalized to the request's display currency
        $minPrice = $priceRange?->min_price !== null ? round((float) $priceRange->min_price, 2) : null;
        $maxPrice = $priceRange?->max_price !== null ? round((float) $priceRange->max_price, 2) : null;

        $data = [
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'currency_code' => $targetCurrency,
            'currency_symbol' => null,
        ];

        // base == target here, so converted_* mirror min/max (rate 1.0) and resolve the symbol
        if ($minPrice !== null) {
            $data = CurrencyConverter::addConvertedPrice($data, $minPrice, $targetCurrency, 'min_price');
        }
        if ($maxPrice !== null) {
            $data = CurrencyConverter::addConvertedPrice($data, $maxPrice, $targetCurrency, 'max_price');
        }
        $data['currency_symbol'] = $data['converted_currency_symbol'] ?? $data['currency_symbol'];

        $data['facilities'] = $availableFacilities;
        $data['property_types'] = $availablePropertyTypes;
        $data['items'] = $items;
        $data['paginator'] = $paginator;

        return $data;
    }

    /**
     * Get property details by slug.
     *
     * @return array<string, mixed>
     */
    public function getPropertyDetails(string $slug, ?int $userId = null): array
    {
        $property = Property::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->withAvg(['reviews' => fn ($q) => $q->where('status', 'published')->where('is_visible', true)], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('status', 'published')->where('is_visible', true)])
            ->with([
                'country:id,name,currency_code,currency_symbol',
                'propertyType:id,name,icon',
                'refState:id,name',
                'refCity:id,name',
                'facilities.category',
                'primaryImages',
                'galleryImages',
                'ruleAnswers.question.propertyRule',
                /*
                'reviews' => fn ($q) => $q->where('status', 'published')
                    ->where('is_visible', true)
                    ->with(['user', 'images'])
                    ->latest()
                    ->limit(5),
                */
                'rooms' => fn ($q) => $q->withAvg(['reviews' => fn ($r) => $r->where('status', 'published')->where('is_visible', true)], 'rating')
                    ->withCount(['reviews' => fn ($r) => $r->where('status', 'published')->where('is_visible', true)])
                    ->with(['roomType.images', 'roomType.facilities.category']),
            ])
            ->first();

        if (! $property) {
            throw ValidationException::withMessages([
                'id' => 'Property not found.',
            ]);
        }

        $isFavorited = $userId && Favorite::where('user_id', $userId)->where('property_id', $property->id)->exists();

        return $this->formatPropertyDetails($property, $isFavorited);
    }

    /**
     * Format full property details.
     *
     * @return array<string, mixed>
     */
    private function formatPropertyDetails(Property $property, bool $isFavorited = false): array
    {
        return [
            'id' => $property->id,
            'is_favorited' => $isFavorited,
            'slug' => $property->slug,
            'name' => $property->name,
            'description' => $property->description,
            'meta_title' => $property->meta_title,
            'meta_description' => $property->meta_description,
            'meta_keywords' => $property->meta_keywords,
            'schema_markup' => $property->schema_markup,
            'country' => [
                'id' => $property->country?->id,
                'name' => $property->country?->name,
                'currency_code' => $property->country?->currency_code,
                'currency_symbol' => $property->country?->currency_symbol,
            ],
            'property_type' => [
                'id' => $property->propertyType?->id,
                'name' => $property->propertyType?->name,
                'icon' => $property->propertyType?->icon_url,
            ],
            'state' => $property->refState?->name,
            'city' => $property->refCity?->name,
            'street_address' => $property->street_address,
            'zip_code' => $property->zip_code,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'phone' => trim(($property->dial_code ? $property->dial_code.' ' : '').$property->phone),
            'email' => $property->email,
            'landline' => $property->landline
                ? trim(($property->landline_dial_code ? $property->landline_dial_code.' ' : '').$property->landline)
                : null,
            'check_in_time' => $property->check_in_time,
            'check_out_time' => $property->check_out_time,
            'rating' => $property->reviews_avg_rating ? (float) number_format($property->reviews_avg_rating, 1) : null,
            'reviews_count' => $property->reviews_count ?? 0,
            'custom_rules' => $property->custom_rules,
            'pets_allowed' => $property->pets_allowed,
            'pet_policy_details' => $property->pet_policy_details,
            'pay_at_property' => $property->pay_at_property,
            'advance_percentage' => (float) $property->advance_percentage,
            'facilities' => $property->facilities->map(fn ($facility) => [
                'id' => $facility->id,
                'name' => $facility->name,
                'icon' => $facility->getIconUrl(),
                'category' => $facility->category?->name,
            ])->toArray(),
            'rules' => $property->ruleAnswers->groupBy(function ($answer) {
                return $answer->question?->propertyRule?->id;
            })->map(function ($answers) {
                $category = $answers->first()->question?->propertyRule;
                if (! $category) {
                    return null;
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon ? asset('storage/'.$category->icon) : null,
                    'items' => $answers->map(fn ($answer) => [
                        'question' => $answer->question?->question_text,
                        'answer' => $answer->answer_value,
                        'type' => $answer->question?->answer_type,
                    ])->values()->toArray(),
                ];
            })->filter()->values()->toArray(),
            'images' => [
                'primary' => $property->primaryImages->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/'.$img->image_path),
                    'media_type' => $img->media_type ?? 'image',
                ])->toArray(),
                'gallery' => $property->galleryImages->groupBy('group_name')->map(fn ($group, $name) => [
                    'group' => $name,
                    'images' => $group->map(fn ($img) => [
                        'id' => $img->id,
                        'url' => asset('storage/'.$img->image_path),
                        'media_type' => $img->media_type ?? 'image',
                    ])->toArray(),
                ])->values()->toArray(),
            ],
            /*
            'recent_reviews' => $property->reviews->map(fn ($review) => [
                'id' => $review->id,
                'user' => [
                    'name' => $review->user?->name,
                    'avatar' => $review->user?->avatar ? asset('storage/'.$review->user->avatar) : null,
                ],
                'rating' => (float) $review->rating,
                'review' => $review->review,
                'date' => $review->created_at->format('M d, Y'),
                'images' => $review->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/'.$img->image_path),
                ])->toArray(),
            ])->toArray(),
            */
            'rooms' => $property->rooms->map(fn ($room) => [
                'id' => $room->id,
                'total_rooms' => $room->total_rooms,
                'room_size' => $room->room_size,
                'base_price_per_night' => (float) $room->base_price_per_night,
                'rating' => $room->reviews_avg_rating ? round($room->reviews_avg_rating, 1) : null,
                'reviews_count' => $room->reviews_count ?? 0,
                'room_type' => [
                    'id' => $room->roomType?->id,
                    'slug' => $room->roomType?->slug,
                    'name' => $room->roomType?->name,
                    'bed_type' => $room->roomType?->bed_type,
                    'max_guests' => $room->roomType?->max_guests,
                    'description' => $room->roomType?->description,
                    'meta_title' => $room->roomType?->meta_title,
                    'meta_description' => $room->roomType?->meta_description,
                    'meta_keywords' => $room->roomType?->meta_keywords,
                    'schema_markup' => $room->roomType?->schema_markup,
                    'images' => $room->roomType?->images->map(fn ($img) => [
                        'id' => $img->id,
                        'url' => asset('storage/'.$img->image_path),
                    ])->toArray() ?? [],
                    'facilities' => $room->roomType?->facilities->map(fn ($facility) => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'icon' => $facility->getIconUrl(),
                        'category' => $facility->category?->name,
                    ])->toArray() ?? [],
                ],
            ])->toArray(),
            'cancellation_policy' => $this->formatCancellationPolicy($property),
        ];
    }

    /**
     * Get rooms for a property.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>  $filters
     */
    /**
     * Resolve property ID — if not provided, use the only active property (single mode).
     */
    public function resolvePropertyId(?string $slug): int
    {
        if ($slug) {
            $property = Property::query()
                ->publiclyVisible()
                ->where('slug', $slug)
                ->first();

            if (! $property) {
                throw ValidationException::withMessages([
                    'property_slug' => 'Property not found.',
                ]);
            }

            return $property->id;
        }

        $activeProperties = Property::query()
            ->publiclyVisible()
            ->limit(2)
            ->pluck('id');

        if ($activeProperties->isEmpty()) {
            throw ValidationException::withMessages([
                'property_slug' => 'No active properties found.',
            ]);
        }

        if ($activeProperties->count() > 1) {
            throw ValidationException::withMessages([
                'property_slug' => 'Multiple properties exist. Please provide a property slug.',
            ]);
        }

        return $activeProperties->first();
    }

    public function resolvePropertyIdFromRoomSlug(string $roomSlug): int
    {
        $propertyRoom = PropertyRoom::query()
            ->where('slug', $roomSlug)
            ->first();

        if (! $propertyRoom) {
            throw ValidationException::withMessages([
                'room_slug' => 'Room not found.',
            ]);
        }

        return $propertyRoom->property_id;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPropertyRooms(int $propertyId, array $filters = []): array
    {
        $property = Property::query()
            ->publiclyVisible()
            ->with([
                'country:id,name,currency_code,currency_symbol',
                'refState:id,name',
                'refCity:id,name',
                'primaryImages:id,property_id,image_path,media_type,sort_order',
            ])
            ->withAvg(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)])
            ->find($propertyId);

        if (! $property) {
            throw ValidationException::withMessages([
                'id' => 'Property not found.',
            ]);
        }

        $currencyCode = $property->country?->currency_code;
        $currencySymbol = $property->country?->currency_symbol;

        $query = $property->rooms()
            ->with(['roomType.images', 'roomType.facilities.category'])
            ->withAvg(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)]);

        if (! empty($filters['room_slug'])) {
            $query->where('slug', $filters['room_slug']);
        }

        $rooms = $query->get();

        $checkIn = $filters['check_in'] ?? null;
        $checkOut = $filters['check_out'] ?? null;
        $requestedRooms = $filters['rooms'] ?? 1;
        $totalGuests = ($filters['adults'] ?? 0) + ($filters['children'] ?? 0);

        // Hard-filter by guest capacity — capacity is per room × number of rooms booked
        if ($totalGuests > 0) {
            $rooms = $rooms->filter(fn ($room) => $room->roomType->max_guests * $requestedRooms >= $totalGuests);
        }

        // Hard-filter by total room count — if property has fewer rooms than requested, can never fulfill
        if ($requestedRooms > 1) {
            $rooms = $rooms->filter(fn ($room) => $room->total_rooms >= $requestedRooms);
        }

        // Date availability — is_available reflects inventory only, not capacity
        if ($checkIn && $checkOut) {
            $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));

            $rooms = $rooms->map(function ($room) use ($checkIn, $checkOut, $requestedRooms, $nights) {
                $available = $this->getAvailableRooms($room->id, $checkIn, $checkOut);

                $room->available_rooms = $available;
                $room->nights = $nights;
                $room->total_price = (float) $room->base_price_per_night * $nights;
                $room->is_available = $available >= $requestedRooms;

                return $room;
            });
        }

        $hasDates = $checkIn && $checkOut;

        // Compute limits (min_price, max_price, and unique amenities) from available $rooms
        $minPrice = $rooms->min('base_price_per_night');
        $maxPrice = $rooms->max('base_price_per_night');

        $allAmenities = collect();
        foreach ($rooms as $r) {
            if ($r->roomType && $r->roomType->facilities) {
                foreach ($r->roomType->facilities as $fac) {
                    $allAmenities->push([
                        'id' => $fac->id,
                        'name' => $fac->name,
                        'icon' => $fac->getIconUrl(),
                        'category' => $fac->category?->name,
                    ]);
                }
            }
        }
        $uniqueAmenities = $allAmenities->unique('id')->values()->toArray();

        // Convert filter prices from target currency to property currency if needed
        $targetCurrency = CurrencyConverter::getTargetCurrency();
        $minPriceFilter = $filters['min_price'] ?? null;
        $maxPriceFilter = $filters['max_price'] ?? null;

        if (($minPriceFilter || $maxPriceFilter) && $targetCurrency !== strtoupper($currencyCode)) {
            // Convert from target currency (e.g., USD) to property currency (e.g., INR)
            // Try both rate directions in case rate is stored in reverse
            $rate = ExchangeRate::getRate($targetCurrency, $currencyCode);
            if (! $rate) {
                // Try reverse direction (property to target) and invert
                $reverseRate = ExchangeRate::getRate($currencyCode, $targetCurrency);
                if ($reverseRate) {
                    $rate = 1 / $reverseRate;
                }
            }

            if ($rate) {
                if ($minPriceFilter) {
                    $minPriceFilter = $minPriceFilter * $rate;
                }
                if ($maxPriceFilter) {
                    $maxPriceFilter = $maxPriceFilter * $rate;
                }
            }
        }

        // Apply price and amenity filters using PHP collections
        if ($minPriceFilter !== null) {
            $rooms = $rooms->filter(fn ($r) => $r->base_price_per_night >= $minPriceFilter);
        }
        if ($maxPriceFilter !== null) {
            $rooms = $rooms->filter(fn ($r) => $r->base_price_per_night <= $maxPriceFilter);
        }
        if (! empty($filters['rate'])) {
            $minRating = (int) $filters['rate'];
            $rooms = $rooms->filter(fn ($r) => $r->reviews_avg_rating >= $minRating);
        }
        if (! empty($filters['amenities'])) {
            $amenityIds = is_array($filters['amenities'])
                ? $filters['amenities']
                : explode(',', $filters['amenities']);

            $rooms = $rooms->filter(function ($r) use ($amenityIds) {
                if (! $r->roomType || ! $r->roomType->facilities) {
                    return false;
                }
                foreach ($amenityIds as $amenityId) {
                    $found = false;
                    foreach ($r->roomType->facilities as $fac) {
                        if ($fac->id == $amenityId || stripos($fac->name, (string) $amenityId) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (! $found) {
                        return false;
                    }
                }

                return true;
            });
        }

        $sortBy = $filters['sort_by'] ?? null;
        if ($sortBy === 'highly_rated') {
            $rooms = $rooms->sortByDesc('reviews_avg_rating');
        } elseif ($sortBy === 'price_low_high') {
            $rooms = $rooms->sortBy('base_price_per_night');
        } elseif ($sortBy === 'price_high_low') {
            $rooms = $rooms->sortByDesc('base_price_per_night');
        }

        $rooms = $rooms->values();

        // Paginate the filtered collection
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        $page = (int) floor($offset / $limit) + 1;
        $total = $rooms->count();
        $pagedRooms = $rooms->slice($offset, $limit)->values();

        $items = $pagedRooms->map(function ($room) use ($hasDates, $currencyCode, $currencySymbol) {
            $data = [
                'id' => $room->id,
                'slug' => $room->slug,
                'total_rooms' => $room->total_rooms,
                'rating' => $room->reviews_avg_rating ? round($room->reviews_avg_rating, 1) : null,
                'reviews_count' => $room->reviews_count ?? 0,
            ];

            if ($hasDates) {
                $data['available_rooms'] = $room->available_rooms;
                $data['nights'] = $room->nights;
                $data['total_price'] = $room->total_price;
                $data['is_available'] = $room->is_available ?? false;
            } elseif (isset($room->is_available)) {
                $data['is_available'] = $room->is_available;
            }

            if ($hasDates) {
                $data = CurrencyConverter::addConvertedPrice(
                    $data,
                    (float) $room->total_price,
                    $currencyCode,
                    'total_price',
                );
            }

            $data['room_size'] = $room->room_size;
            $data['base_price_per_night'] = (float) $room->base_price_per_night;
            $data['currency_code'] = $currencyCode;
            $data['currency_symbol'] = $currencySymbol;

            $data = CurrencyConverter::addConvertedPrice(
                $data,
                (float) $room->base_price_per_night,
                $currencyCode,
            );
            $data['room_type'] = [
                'id' => $room->roomType?->id,
                'slug' => $room->roomType?->slug,
                'name' => $room->roomType?->name,
                'bed_type' => $room->roomType?->bed_type,
                'max_guests' => $room->roomType?->max_guests,
                'description' => $room->roomType?->description,
                'meta_title' => $room->roomType?->meta_title,
                'meta_description' => $room->roomType?->meta_description,
                'meta_keywords' => $room->roomType?->meta_keywords,
                'schema_markup' => $room->roomType?->schema_markup,
                'images' => $room->roomType?->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/'.$img->image_path),
                ])->toArray() ?? [],
                'facilities' => $room->roomType?->facilities->map(fn ($facility) => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'icon' => $facility->getIconUrl(),
                    'category' => $facility->category?->name,
                ])->toArray() ?? [],
            ];

            return $data;
        })->toArray();

        // Build property info
        $propertyAddress = implode(', ', array_filter([
            $property->street_address,
            $property->refCity?->name,
            $property->refState?->name,
            $property->country?->name,
            $property->zip_code,
        ]));

        $propertyData = [
            'slug' => $property->slug,
            'name' => $property->name,
            'rating' => $property->reviews_avg_rating ? round((float) $property->reviews_avg_rating, 1) : null,
            'review_count' => $property->reviews_count ?? 0,
            'address' => $propertyAddress,
            'latitude' => $property->latitude ? (float) $property->latitude : null,
            'longitude' => $property->longitude ? (float) $property->longitude : null,
            'image' => $property->primaryImages->firstWhere('media_type', 'image')?->image_path
                ? asset('storage/'.$property->primaryImages->firstWhere('media_type', 'image')->image_path)
                : null,
            'pets_allowed' => $property->pets_allowed,
            'pet_policy_details' => $property->pet_policy_details,
            'cancellation_policy' => $this->formatCancellationPolicy($property),
        ];

        $data = [
            'property' => $propertyData,
            'min_price' => $minPrice !== null ? (float) $minPrice : null,
            'max_price' => $maxPrice !== null ? (float) $maxPrice : null,
            'currency_code' => $currencyCode ?? 'INR',
            'currency_symbol' => $currencySymbol ?? '₹',
        ];

        if ($minPrice !== null) {
            $data = CurrencyConverter::addConvertedPrice($data, (float) $minPrice, (string) ($currencyCode ?? 'INR'), 'min_price');
        }
        if ($maxPrice !== null) {
            $data = CurrencyConverter::addConvertedPrice($data, (float) $maxPrice, (string) ($currencyCode ?? 'INR'), 'max_price');
        }

        $data['facilities'] = $uniqueAmenities;
        $data['items'] = $items;
        $data['pagination'] = [
            'total' => $total,
            'limit' => (int) $limit,
            'offset' => (int) $offset,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $limit),
            'has_more' => ($offset + $limit) < $total,
        ];

        return $data;
    }

    /**
     * Get minimum available rooms for a property_room across a date range.
     */
    private function getAvailableRooms(int $propertyRoomId, string $checkIn, string $checkOut): int
    {
        $dates = collect();
        $current = Carbon::parse($checkIn);
        $end = Carbon::parse($checkOut);

        while ($current->lt($end)) {
            $dates->push($current->toDateString());
            $current->addDay();
        }

        $propertyRoom = PropertyRoom::find($propertyRoomId);
        if (! $propertyRoom) {
            return 0;
        }

        $totalRooms = $propertyRoom->total_rooms;

        $inventory = RoomInventory::query()
            ->where('property_room_id', $propertyRoomId)
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn ($record) => $record->date->format('Y-m-d'));

        // Direct lock check — single query covering the full range.
        // Acts as source of truth in case locked_rooms in RoomInventory is out of sync.
        $activeLocks = InventoryLock::query()
            ->where('property_room_id', $propertyRoomId)
            ->where('status', InventoryLockStatus::Active)
            ->where('expires_at', '>', now())
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->get(['check_in', 'check_out', 'quantity']);

        // Find minimum availability across all dates
        $minAvailable = PHP_INT_MAX;
        foreach ($dates as $date) {
            $record = $inventory->get($date);
            $bookedRooms = $record?->booked_rooms ?? 0;
            $lockedFromInventory = $record?->locked_rooms ?? 0;

            // Sum quantity of active locks that cover this specific date
            $lockedFromLocks = $activeLocks->sum(function ($lock) use ($date) {
                return ($date >= $lock->check_in->format('Y-m-d') && $date < $lock->check_out->format('Y-m-d'))
                    ? $lock->quantity
                    : 0;
            });

            $available = $totalRooms - $bookedRooms - max($lockedFromInventory, $lockedFromLocks);

            $minAvailable = min($minAvailable, $available);
        }

        return max(0, $minAvailable === PHP_INT_MAX ? 0 : $minAvailable);
    }

    /**
     * Format property for compact listing.
     *
     * @return array<string, mixed>
     */
    public function formatCompactProperty(Property $property, bool $isFavorited = false): array
    {
        $primaryImage = $property->primaryImages->firstWhere('media_type', 'image') ?? $property->primaryImages->first();
        $currencyCode = $property->country?->currency_code;
        $currencySymbol = $property->country?->currency_symbol;

        // Starting price — cheapest room
        $startingPrice = $property->rooms->min('base_price_per_night');

        // Facilities — first 3 as objects + remaining count
        $allFacilities = $property->facilities;
        $displayFacilities = $allFacilities->take(3)->map(fn ($fac) => [
            'id' => $fac->id,
            'name' => $fac->name,
            'icon' => $fac->getIconUrl(),
            'category' => $fac->category?->name,
        ])->toArray();
        $moreFacilitiesCount = max(0, $allFacilities->count() - 3);

        $data = [
            'id' => $property->id,
            'slug' => $property->slug,
            'name' => $property->name,
            'country_id' => $property->country_id,
            'country_name' => $property->country?->name,
            'state' => $property->refState?->name,
            'city' => $property->refCity?->name,
            'street_address' => $property->street_address,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'distance_km' => isset($property->distance_km) ? round((float) $property->distance_km, 2) : null,
            'image' => $primaryImage ? asset('storage/'.$primaryImage->image_path) : null,
            'images' => $property->primaryImages->where('media_type', 'image')
                ->map(fn ($img) => asset('storage/'.$img->image_path))
                ->values()
                ->toArray(),
            'starting_price' => $startingPrice ? (float) $startingPrice : null,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'pay_at_property' => $property->pay_at_property,
            'advance_percentage' => $property->advance_percentage,
            'pets_allowed' => $property->pets_allowed,
            'pet_policy_details' => $property->pet_policy_details,
            'facilities' => $displayFacilities,
            'more_facilities_count' => $moreFacilitiesCount,
            'rating' => $property->reviews_avg_rating ? round($property->reviews_avg_rating, 1) : null,
            'reviews_count' => $property->reviews_count ?? 0,
            'single_room_type_available' => isset($property->single_room_type_available)
                ? (bool) $property->single_room_type_available
                : null,
            'is_favorited' => $isFavorited,
        ];

        // Add converted price if currency header present
        if ($startingPrice && $currencyCode) {
            $data = CurrencyConverter::addConvertedPrice(
                $data,
                (float) $startingPrice,
                $currencyCode,
                'starting_price',
            );
        }

        return $data;
    }

    /**
     * Load and format the global cancellation policy for a property.
     *
     * @return array{free_cancellation_until: null, cancellation_cutoff_time: string|null, rules: array<int, array{days_before_checkin: int, refund_percentage: int}>}
     */
    private function formatCancellationPolicy(Property $property): array
    {
        $policy = app(CancellationPolicyService::class)->resolvePolicyForRefund($property);

        if (! $policy) {
            return [
                'free_cancellation_until' => null,
                'cancellation_cutoff_time' => null,
                'rules' => [],
            ];
        }

        $policy->loadMissing(['rules' => fn ($q) => $q->orderByDesc('days_before_checkin')]);

        return [
            'free_cancellation_until' => null,
            'cancellation_cutoff_time' => $policy->cancellation_cutoff_time,
            'rules' => $policy->rules->map(fn ($rule) => [
                'days_before_checkin' => $rule->days_before_checkin,
                'refund_percentage' => $rule->refund_percentage,
            ])->toArray(),
        ];
    }
}
