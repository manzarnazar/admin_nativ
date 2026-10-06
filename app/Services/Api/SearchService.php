<?php

namespace App\Services\Api;

use App\Enums\ReviewStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Property;
use App\Services\OpenStreetMapService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SearchService
{
    private const LOCATIONS_LIMIT = 8;

    private const PRIORITY_CITIES_LIMIT = 3;

    private const PROPERTIES_LIMIT = 5;

    public function __construct(
        private readonly OpenStreetMapService $openStreetMapService,
    ) {}

    /**
     * Combined search suggestions: cities/places + matching properties.
     *
     * @return array{locations: array<int, mixed>, properties: array<int, mixed>}
     */
    public function suggest(string $query, ?int $countryId = null, ?float $lat = null, ?float $lng = null, ?string $userCountry = null): array
    {
        return [
            'locations' => $this->locations($query, $countryId, $lat, $lng, $userCountry),
            'properties' => $this->properties($query, $countryId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function locations(string $query, ?int $countryId, ?float $lat = null, ?float $lng = null, ?string $userCountry = null): array
    {
        $priority = $this->priorityCities($query, $countryId);

        $fill = $this->fillLocations($query, $countryId, $priority, $lat, $lng, $userCountry);

        $remaining = max(0, self::LOCATIONS_LIMIT - count($priority));

        return array_merge($priority, array_slice($fill, 0, $remaining));
    }

    /**
     * Cities where we already have publicly visible inventory — always shown
     * first so a searched city with real properties can never be crowded out
     * by more "popular" places from the map provider.
     *
     * @return array<int, array<string, mixed>>
     */
    private function priorityCities(string $query, ?int $countryId): array
    {
        $cities = City::query()
            ->active()
            ->when($countryId, fn ($q) => $q->forCountry($countryId))
            ->where('name', 'like', "{$query}%")
            ->whereHas('properties', fn ($q) => $q->publiclyVisible())
            ->withCount(['properties as property_count' => fn ($q) => $q->publiclyVisible()])
            ->with(['state:id,name', 'country:id,name'])
            ->orderByDesc('property_count')
            ->limit(self::PRIORITY_CITIES_LIMIT)
            ->get();

        return $cities->map(fn (City $city): array => [
            'source' => 'local',
            'ref_city_id' => $city->ref_city_id,
            'name' => $city->name,
            'state' => $city->state?->name,
            'country' => $city->country?->name,
            'property_count' => $city->property_count,
            'latitude' => $city->latitude !== null ? (float) $city->latitude : null,
            'longitude' => $city->longitude !== null ? (float) $city->longitude : null,
        ])->values()->toArray();
    }

    /**
     * General place suggestions from the map provider, deduped against the
     * priority cities already selected.
     *
     * TODO(map-provider): Google-mode support is not implemented. Google's
     * Text Search "includedType" filter explicitly excludes geopolitical
     * queries (cities/localities), so it can't be restricted to city-type
     * results the way Photon's "layer=city" filter can. Doing this properly
     * needs the Autocomplete (New) API + a Place Details call per selection
     * (Autocomplete doesn't return lat/lng). Add this when a customer running
     * map_provider=google actually needs it.
     *
     * @param  array<int, array<string, mixed>>  $priority
     * @return array<int, array<string, mixed>>
     */
    private function fillLocations(string $query, ?int $countryId, array $priority, ?float $lat = null, ?float $lng = null, ?string $userCountry = null): array
    {
        $countryCode = $countryId ? Country::whereKey($countryId)->value('iso_code') : null;

        $cacheKey = 'search:locations:osm:'.md5($query.':'.$countryCode.':'.$lat.':'.$lng);

        $results = Cache::remember($cacheKey, now()->addMinutes(10), fn () => $this->openStreetMapService->autocompleteCities(
            query: $query,
            limit: self::LOCATIONS_LIMIT + 2,
            countryCode: $countryCode,
            lat: $lat,
            lng: $lng,
        ));

        $seen = collect($priority)->map(fn (array $city) => $this->dedupeKey($city['name'], $city['country']))->all();

        $mapped = collect($results)
            ->reject(fn (array $place) => in_array($this->dedupeKey($place['name'], $place['country']), $seen, true))
            ->map(fn (array $place): array => [
                'source' => 'osm',
                'place_id' => $place['place_id'],
                'name' => $place['name'],
                'state' => $place['state'],
                'country' => $place['country'],
                'property_count' => null,
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
            ])
            ->values()
            ->toArray();

        if ($userCountry) {
            usort($mapped, function ($a, $b) use ($userCountry) {
                $aLocal = (strcasecmp((string) $a['country'], $userCountry) === 0) ? 1 : 0;
                $bLocal = (strcasecmp((string) $b['country'], $userCountry) === 0) ? 1 : 0;
                return $bLocal <=> $aLocal;
            });
        }

        return $mapped;
    }

    private function dedupeKey(?string $name, ?string $country): string
    {
        return Str::lower(trim($name ?? '')).'|'.Str::lower(trim($country ?? ''));
    }

    /**
     * Property name/slug/address matches — a village or locality name typed
     * by the customer (e.g. "Sukhpar") only ever appears in the free-text
     * street_address, never in a structured city field.
     *
     * @return array<int, array<string, mixed>>
     */
    private function properties(string $query, ?int $countryId): array
    {
        $properties = Property::query()
            ->publiclyVisible()
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->matchesSearchTerms($query, ['name', 'slug', 'street_address'])
            ->selectRaw('properties.*, CASE WHEN name LIKE ? OR slug LIKE ? THEN 1 ELSE 0 END AS name_match', ["%{$query}%", "%{$query}%"])
            ->with(['refCity:id,name', 'primaryImages:id,property_id,image_path,media_type,sort_order'])
            ->withAvg(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('status', ReviewStatus::Published)->where('is_visible', true)])
            ->orderByDesc('name_match')
            ->orderByDesc('reviews_count')
            ->limit(self::PROPERTIES_LIMIT)
            ->get();

        return $properties->map(fn (Property $property): array => [
            'id' => $property->id,
            'name' => $property->name,
            'slug' => $property->slug,
            'city' => $property->refCity?->name,
            'rating' => $property->reviews_avg_rating ? round((float) $property->reviews_avg_rating, 1) : null,
            'review_count' => $property->reviews_count ?? 0,
            'image' => $property->primaryImages->firstWhere('media_type', 'image')?->image_path
                ? asset('storage/'.$property->primaryImages->firstWhere('media_type', 'image')->image_path)
                : null,
        ])->values()->toArray();
    }
}
