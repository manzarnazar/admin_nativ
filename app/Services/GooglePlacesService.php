<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private const BASE_URL = 'https://places.googleapis.com/v1/places';

    /**
     * Fetch nearby places by type using Google Places API (New) Nearby Search.
     *
     * @return array<int, array{place_id: string, name: string, address: string, latitude: float, longitude: float, rating: float|null}>
     */
    public function searchNearby(float $lat, float $lng, string $placeType, int $radius, int $maxResults = 10): array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey) {
            return [];
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.rating',
        ])->post(self::BASE_URL.':searchNearby', [
            'includedTypes' => [$placeType],
            'maxResultCount' => min($maxResults, 20),
            'locationRestriction' => [
                'circle' => [
                    'center' => ['latitude' => $lat, 'longitude' => $lng],
                    'radius' => (float) $radius,
                ],
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('Google Places searchNearby failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'type' => $placeType,
            ]);

            return [];
        }

        return array_map(fn (array $place): array => [
            'place_id' => $place['id'] ?? '',
            'name' => $place['displayName']['text'] ?? '',
            'address' => $place['formattedAddress'] ?? '',
            'latitude' => $place['location']['latitude'] ?? 0,
            'longitude' => $place['location']['longitude'] ?? 0,
            'rating' => $place['rating'] ?? null,
        ], $response->json('places', []));
    }

    /**
     * Search for places using Google Places API (New) Text Search.
     *
     * @return array<int, array{place_id: string, name: string, address: string, latitude: float, longitude: float, rating: float|null}>
     */
    public function searchPlaces(string $query, int $maxResults = 10, ?string $countryCode = null, ?float $lat = null, ?float $lng = null): array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey) {
            return [];
        }

        $body = [
            'textQuery' => $query,
            'pageSize' => $maxResults,
        ];

        if (filled($countryCode)) {
            $body['regionCode'] = strtolower($countryCode);
        }

        if ($lat !== null && $lng !== null) {
            $body['locationBias'] = [
                'circle' => [
                    'center' => ['latitude' => $lat, 'longitude' => $lng],
                    'radius' => 50000.0,
                ],
            ];
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.rating,places.addressComponents',
        ])->post(self::BASE_URL.':searchText', $body);

        if (! $response->successful()) {
            Log::warning('Google Places searchText failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'query' => $query,
            ]);

            return [];
        }

        $places = $response->json('places', []);

        $results = array_map(fn (array $place): array => [
            'place_id' => $place['id'] ?? '',
            'name' => $place['displayName']['text'] ?? '',
            'address' => $place['formattedAddress'] ?? '',
            'latitude' => $place['location']['latitude'] ?? 0,
            'longitude' => $place['location']['longitude'] ?? 0,
            'rating' => $place['rating'] ?? null,
            'country_code' => $this->extractCountryCode($place['addressComponents'] ?? []),
        ], $places);

        if (filled($countryCode)) {
            $results = array_values(array_filter(
                $results,
                fn (array $place): bool => empty($place['country_code']) || strcasecmp($place['country_code'], $countryCode) === 0,
            ));
        }

        return $results;
    }

    /**
     * Extract the ISO 3166-1 alpha-2 country code from Google address components.
     */
    private function extractCountryCode(array $addressComponents): string
    {
        foreach ($addressComponents as $component) {
            if (in_array('country', $component['types'] ?? [], true)) {
                return $component['shortText'] ?? '';
            }
        }

        return '';
    }

    /**
     * Whether a usable server-side Places API key is configured.
     */
    public function hasApiKey(): bool
    {
        return filled($this->getApiKey());
    }

    /**
     * Resolve the server-side Places API key, falling back to the legacy
     * shared key so existing installs keep working until the new key is set.
     */
    private function getApiKey(): ?string
    {
        return Setting::get('google_places_server_key')
            ?: Setting::get('google_maps_api_key');
    }

    /**
     * Calculate distance between two coordinates using Haversine formula.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
