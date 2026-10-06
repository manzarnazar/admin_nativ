<?php

namespace App\Jobs;

use App\Models\City;
use App\Models\NearbyPlace;
use App\Models\NearbyPlaceCategory;
use App\Services\GooglePlacesService;
use App\Services\OpenStreetMapService;
use App\Support\MapProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;

class FetchCityNearbyPlacesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    // Worst case: 8 categories × 3 attempts × (10s primary + 10s fallback + 4s sleep) ≈ 576s.
    public int $timeout = 600;

    public function __construct(
        public readonly City $city,
        public readonly ?int $categoryId = null,
    ) {}

    public function handle(GooglePlacesService $googleService, OpenStreetMapService $osmService): void
    {
        if (! filled($this->city->latitude) || ! filled($this->city->longitude)) {
            return;
        }

        $isOsm = MapProvider::isOsm();

        $categories = NearbyPlaceCategory::query()
            ->where('is_active', true)
            ->when(
                $isOsm,
                fn ($q) => $q->whereNotNull('osm_place_type'),
                fn ($q) => $q->whereNotNull('google_place_type'),
            )
            ->when($this->categoryId, fn ($q) => $q->where('id', $this->categoryId))
            ->get();

        foreach ($categories as $category) {
            $radiusMeters = $category->radius * 1000;
            $placeType = $isOsm ? $category->osm_place_type : $category->google_place_type;

            $results = [];
            $maxAttempts = 3;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $results = $isOsm
                        ? $osmService->searchNearby(
                            lat: (float) $this->city->latitude,
                            lng: (float) $this->city->longitude,
                            osmType: $category->osm_place_type,
                            radiusMeters: $radiusMeters,
                            maxResults: $category->total_places,
                        )
                        : $googleService->searchNearby(
                            lat: (float) $this->city->latitude,
                            lng: (float) $this->city->longitude,
                            placeType: $category->google_place_type,
                            radius: $radiusMeters,
                            maxResults: $category->total_places,
                        );

                    // Success! Break out of the retry loop
                    break;
                } catch (\Throwable $e) {
                    if ($attempt === $maxAttempts) {
                        activity()
                            ->performedOn($this->city)
                            ->event('nearby_places_fetch_failed')
                            ->withProperties([
                                'category' => $category->name,
                                'place_type' => $placeType,
                                'radius_km' => $category->radius,
                                'error' => $e->getMessage(),
                            ])
                            ->log("Nearby places fetch failed for {$this->city->name} / {$category->name}");

                        // Skip this category and continue — a single API failure should not
                        // abort all remaining categories for the city.
                        sleep(2);

                        continue 2; // Continue the outer $categories foreach loop
                    }

                    // Transient error (504, 429). Sleep for a few seconds before retrying this category.
                    sleep(4);
                }
            }

            $saved = 0;
            foreach ($results as $result) {
                if (empty($result['place_id'])) {
                    continue;
                }

                $values = [
                    'nearby_place_category_id' => $category->id,
                    'name' => $result['name'],
                    'address' => $result['address'] ?? null,
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'rating' => $result['rating'] ?? null,
                    'deleted_at' => null,
                ];

                // UPDATE first (covers both live and soft-deleted rows).
                // If nothing matched, INSERT as a new record.
                // Catch the unique violation that can occur when two concurrent
                // jobs both find 0 rows and both attempt the INSERT simultaneously.
                $affected = NearbyPlace::withTrashed()
                    ->where('city_id', $this->city->id)
                    ->where('google_place_id', $result['place_id'])
                    ->update($values);

                if ($affected === 0) {
                    try {
                        NearbyPlace::query()->create(array_merge($values, [
                            'city_id' => $this->city->id,
                            'google_place_id' => $result['place_id'],
                        ]));
                    } catch (UniqueConstraintViolationException) {
                        // A concurrent job inserted this place between our UPDATE
                        // check and our INSERT. The record is already saved; skip.
                        continue;
                    }
                }

                $saved++;
            }

            activity()
                ->performedOn($this->city)
                ->event('nearby_places_fetched')
                ->withProperties([
                    'category' => $category->name,
                    'place_type' => $placeType,
                    'radius_km' => $category->radius,
                    'results_from_api' => count($results),
                    'saved_to_db' => $saved,
                ])
                ->log("Fetched nearby places for {$this->city->name} / {$category->name}: {$saved} saved");

            sleep(2);
        }
    }
}
