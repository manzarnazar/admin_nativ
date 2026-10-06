<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenStreetMapService
{
    protected function getUserAgent(): string
    {
        return (string) config('app.name', 'TestfilaApp').'/1.0 ('.url('/').')';
    }

    /** CDN load-balanced fallback for the official Overpass API (more reliable than community mirrors). */
    private const OVERPASS_FALLBACKS = [
        'https://lz4.overpass-api.de/api/interpreter',
    ];

    /**
     * Search for nearby places using the Overpass API.
     *
     * The $osmType value can be a single tag ("amenity=restaurant") or a
     * comma-separated compound tag ("amenity=place_of_worship,religion=hindu").
     * Each pair is turned into an Overpass tag filter, so compound types produce
     * precise results (Hindu Temple ≠ all places of worship).
     *
     * @return array<int, array{place_id: string, name: string, address: string, latitude: float, longitude: float, rating: float|null}>
     */
    public function searchNearby(float $lat, float $lng, string $osmType, int $radiusMeters, int $maxResults = 10): array
    {
        $tagFilters = $this->buildTagFilters($osmType);

        // Scale Overpass internal timeout with search area; cap at 60s (Overpass limit).
        // HTTP client timeout gets 5s of buffer on top so the server can respond.
        $overpassTimeout = min(60, max(15, (int) ($radiusMeters / 1000) * 2));

        $query = '[out:json][timeout:'.$overpassTimeout.'];'
            .'(node'.$tagFilters.'(around:'.$radiusMeters.','.$lat.','.$lng.');'
            .'way'.$tagFilters.'(around:'.$radiusMeters.','.$lat.','.$lng.');'
            .');out center '.$maxResults.';';

        $data = $this->executeOverpassQuery($query, $overpassTimeout + 5);

        if (! isset($data['elements'])) {
            return [];
        }

        $results = [];
        foreach ($data['elements'] as $element) {
            $name = $element['tags']['name'] ?? null;

            if (! $name) {
                continue;
            }

            // Ways have a "center" key; nodes use lat/lon directly.
            $elementLat = $element['lat'] ?? $element['center']['lat'] ?? null;
            $elementLng = $element['lon'] ?? $element['center']['lon'] ?? null;

            if ($elementLat === null || $elementLng === null) {
                continue;
            }

            $results[] = [
                'place_id' => 'osm:'.$element['id'],
                'name' => $name,
                'address' => $this->buildAddress($element['tags'] ?? []),
                'latitude' => (float) $elementLat,
                'longitude' => (float) $elementLng,
                'rating' => null,
            ];

            if (count($results) >= $maxResults) {
                break;
            }
        }

        return $results;
    }

    /**
     * Execute an Overpass query via POST, trying the primary endpoint then community mirrors.
     * Throws RuntimeException if every endpoint fails so the calling job can retry.
     *
     * @return array<string, mixed>
     */
    /**
     * Try every Overpass endpoint in order and return the first successful JSON response.
     * Collects per-endpoint diagnostics; on total failure throws RuntimeException with them.
     *
     * @param  array<string, mixed>|null  $diagnostics  Pass a reference to collect per-endpoint results.
     * @return array<string, mixed>
     */
    private function executeOverpassQuery(string $query, int $httpTimeout = 25, ?array &$diagnostics = null): array
    {
        $endpoints = array_unique(array_merge(
            [(string) config('maps.overpass_url')],
            self::OVERPASS_FALLBACKS,
        ));

        $diagnostics = [];

        foreach ($endpoints as $index => $endpoint) {
            $entry = ['endpoint' => $endpoint, 'status' => null, 'content_type' => null, 'error' => null, 'ok' => false];

            // The fallback is now an official CDN node, so we give it the full timeout.
            $endpointTimeout = $httpTimeout;

            // Give the Overpass API a tiny breathing room before hitting the fallback
            if ($index > 0) {
                sleep(2);
            }

            try {
                $response = Http::withHeaders(['User-Agent' => $this->getUserAgent()])
                    ->timeout($endpointTimeout)
                    ->get($endpoint, ['data' => $query]);

                $entry['status'] = $response->status();
                $entry['content_type'] = $response->header('Content-Type');

                if ($response->successful() && str_contains($entry['content_type'] ?? '', 'json')) {
                    $entry['ok'] = true;
                    $diagnostics[] = $entry;

                    return $response->json() ?? [];
                }

                $entry['error'] = "HTTP {$entry['status']} — non-JSON or error response";
                Log::warning('Overpass endpoint skipped', $entry);
            } catch (\Exception $e) {
                $entry['error'] = $e->getMessage();
                Log::warning('Overpass endpoint exception', $entry);
            }

            $diagnostics[] = $entry;
        }

        throw new \RuntimeException('All Overpass API endpoints failed. Will retry.');
    }

    /**
     * Run a live Overpass query and return results + per-endpoint diagnostics.
     * Intended for the debug API endpoint only — never use in production flows.
     *
     * @return array{results: array<int, mixed>, endpoints: array<int, mixed>, query: string}
     */
    public function debugSearchNearby(float $lat, float $lng, string $osmType, int $radiusMeters, int $maxResults = 10): array
    {
        $tagFilters = $this->buildTagFilters($osmType);

        // Debug path: keep the Overpass query timeout short so 3 endpoints fit under PHP's 30s limit.
        $overpassTimeout = 8;

        $query = '[out:json][timeout:'.$overpassTimeout.'];'
            .'(node'.$tagFilters.'(around:'.$radiusMeters.','.$lat.','.$lng.');'
            .'way'.$tagFilters.'(around:'.$radiusMeters.','.$lat.','.$lng.');'
            .');out center '.$maxResults.';';

        $diagnostics = [];

        try {
            // 8s Overpass timeout + 2s HTTP buffer = 10s per endpoint, 30s max for 3 endpoints.
            $data = $this->executeOverpassQuery($query, $overpassTimeout + 2, $diagnostics);
            $elements = $data['elements'] ?? [];

            $results = [];
            foreach ($elements as $element) {
                $name = $element['tags']['name'] ?? null;
                if (! $name) {
                    continue;
                }
                $elat = $element['lat'] ?? $element['center']['lat'] ?? null;
                $elng = $element['lon'] ?? $element['center']['lon'] ?? null;
                if ($elat === null || $elng === null) {
                    continue;
                }
                $results[] = [
                    'place_id' => 'osm:'.$element['id'],
                    'name' => $name,
                    'address' => $this->buildAddress($element['tags'] ?? []),
                    'latitude' => (float) $elat,
                    'longitude' => (float) $elng,
                ];
                if (count($results) >= $maxResults) {
                    break;
                }
            }
        } catch (\RuntimeException) {
            $results = [];
        }

        return [
            'query' => $query,
            'endpoints' => $diagnostics,
            'results_count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Search for an address using the Nominatim geocoding API.
     *
     * @return array<int, array{place_id: string, display_name: string, lat: float, lon: float}>
     */
    public function searchAddress(string $query, int $limit = 5, ?string $countryCode = null, ?float $lat = null, ?float $lng = null): array
    {
        $params = [
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => $limit,
        ];

        if ($countryCode) {
            $params['countrycodes'] = strtolower($countryCode);
        }

        if ($lat !== null && $lng !== null) {
            // Restrict results to a ~55km box around the city. Nominatim viewbox = left,top,right,bottom (lon,lat,lon,lat).
            $params['viewbox'] = sprintf('%f,%f,%f,%f', $lng - 0.5, $lat + 0.5, $lng + 0.5, $lat - 0.5);
            $params['bounded'] = 1;
        }

        $response = Http::withHeaders(['User-Agent' => $this->getUserAgent()])
            ->timeout(10)
            ->get(config('maps.nominatim_url').'/search', $params);

        if (! $response->successful()) {
            Log::warning('Nominatim searchAddress failed', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return [];
        }

        return array_map(fn (array $item): array => [
            'place_id' => 'osm:'.$item['osm_id'],
            'display_name' => $item['display_name'],
            'lat' => (float) $item['lat'],
            'lon' => (float) $item['lon'],
            'address' => $item['address'] ?? [],
        ], $response->json() ?? []);
    }

    /**
     * Autocomplete cities/towns/villages using the Photon geocoding API.
     *
     * Unlike Nominatim, Photon matches partial words as the user types and its
     * "city" layer already buckets place=city/town/village together, so no
     * further OSM-tag filtering is needed here.
     *
     * @return array<int, array{place_id: string, name: string, state: ?string, country: ?string, latitude: float, longitude: float}>
     */
    public function autocompleteCities(string $query, int $limit = 8, ?string $countryCode = null, ?float $lat = null, ?float $lng = null): array
    {
        $params = [
            'q' => $query,
            'limit' => $limit,
            'lang' => 'en',
            'layer' => 'city',
        ];

        if ($countryCode) {
            $params['countrycode'] = strtolower($countryCode);
        }

        if ($lat !== null && $lng !== null) {
            $params['lat'] = $lat;
            $params['lon'] = $lng;
            $params['location_bias_scale'] = 0.5;
        }

        $response = Http::withHeaders(['User-Agent' => $this->getUserAgent()])
            ->timeout(10)
            ->get(config('maps.photon_url').'/api', $params);

        if (! $response->successful()) {
            Log::warning('Photon autocompleteCities failed', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return [];
        }

        return array_map(fn (array $feature): array => [
            'place_id' => 'osm:'.$feature['properties']['osm_id'],
            'name' => $feature['properties']['name'] ?? '',
            'state' => $feature['properties']['state'] ?? null,
            'country' => $feature['properties']['country'] ?? null,
            'latitude' => (float) ($feature['geometry']['coordinates'][1] ?? 0),
            'longitude' => (float) ($feature['geometry']['coordinates'][0] ?? 0),
        ], $response->json('features', []));
    }

    /**
     * Parse "amenity=restaurant" or "amenity=place_of_worship,religion=hindu"
     * into Overpass tag filter strings like ["amenity"="restaurant"]["religion"="hindu"].
     */
    private function buildTagFilters(string $osmType): string
    {
        $filters = '';

        foreach (explode(',', $osmType) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if ($key && $value) {
                $filters .= '["'.trim($key).'"="'.trim($value).'"]';
            }
        }

        return $filters;
    }

    /**
     * Build a human-readable address string from OSM tags.
     */
    private function buildAddress(array $tags): string
    {
        $parts = array_filter([
            $tags['addr:housenumber'] ?? null,
            $tags['addr:street'] ?? null,
            $tags['addr:city'] ?? null,
        ]);

        return implode(', ', $parts);
    }
}
