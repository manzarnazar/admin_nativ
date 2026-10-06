<?php

namespace App\Services;

use App\Enums\SetupTask;
use App\Jobs\FetchCityNearbyPlacesJob;
use App\Models\City;
use App\Models\CountrySetupTask;
use App\Models\RefCity;
use App\Models\RefState;
use App\Models\State;
use Illuminate\Support\Str;

class CityService
{
    public function createCity(array $data, int $countryId): City
    {
        $refState = RefState::findOrFail($data['ref_state_id']);
        $state = $this->findOrCreateState($refState, $countryId);
        $cityData = $this->resolveCityData($data['ref_city_id'], $refState);

        $city = City::query()->create([
            'ref_city_id' => $cityData['ref_city_id'],
            'country_id' => $countryId,
            'state_id' => $state->id,
            'name' => $cityData['name'],
            'slug' => $this->generateUniqueSlug($cityData['name']),
            'latitude' => $cityData['latitude'],
            'longitude' => $cityData['longitude'],
            'status' => $data['status'],
        ]);

        $isFirstCity = City::query()
            ->forCountry($countryId)
            ->count() === 1;

        if ($isFirstCity) {
            CountrySetupTask::markComplete(SetupTask::Cities, $countryId);
        }

        FetchCityNearbyPlacesJob::dispatch($city);

        return $city;
    }

    /**
     * Ensure the operational city record exists for a given ref_city_id + country.
     * If it doesn't exist yet, creates it and queues a nearby-places fetch.
     * Only dispatches the job — never blocks the caller.
     */
    public function ensureOperationalCity(int $refCityId, int $countryId): void
    {
        $alreadyExists = City::query()
            ->where('ref_city_id', $refCityId)
            ->where('country_id', $countryId)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $refCity = RefCity::findOrFail($refCityId);
        $refState = RefState::findOrFail($refCity->state_id);
        $state = $this->findOrCreateState($refState, $countryId);

        $city = City::query()->create([
            'ref_city_id' => $refCity->id,
            'country_id' => $countryId,
            'state_id' => $state->id,
            'name' => $refCity->name,
            'slug' => $this->generateUniqueSlug($refCity->name),
            'latitude' => $refCity->latitude,
            'longitude' => $refCity->longitude,
            'status' => 'active',
        ]);

        FetchCityNearbyPlacesJob::dispatch($city);
    }

    public function updateCity(City $city, array $data, int $countryId): City
    {
        $refState = RefState::findOrFail($data['ref_state_id']);
        $state = $this->findOrCreateState($refState, $countryId);
        $cityData = $this->resolveCityData($data['ref_city_id'], $refState);

        $city->update([
            'ref_city_id' => $cityData['ref_city_id'],
            'state_id' => $state->id,
            'name' => $cityData['name'],
            'latitude' => $cityData['latitude'],
            'longitude' => $cityData['longitude'],
            'status' => $data['status'],
        ]);

        return $city;
    }

    /**
     * Resolve city data from either a ref_city or a state-as-city fallback.
     *
     * @return array{ref_city_id: int|null, name: string, latitude: string|null, longitude: string|null}
     */
    private function resolveCityData(string $refCityIdRaw, RefState $refState): array
    {
        // State-as-city fallback: "state:123" means use the state as the city
        if (str_starts_with($refCityIdRaw, 'state:')) {
            return [
                'ref_city_id' => null,
                'name' => $refState->name,
                'latitude' => $refState->latitude,
                'longitude' => $refState->longitude,
            ];
        }

        $refCity = RefCity::findOrFail((int) $refCityIdRaw);

        return [
            'ref_city_id' => $refCity->id,
            'name' => $refCity->name,
            'latitude' => $refCity->latitude,
            'longitude' => $refCity->longitude,
        ];
    }

    /**
     * Slug is generated once at creation and never changes afterward — same
     * convention as FaqTopicService, so links/filters built against it stay stable.
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 2;

        while (City::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function findOrCreateState(RefState $refState, int $countryId): State
    {
        return State::query()->firstOrCreate(
            ['ref_state_id' => $refState->id],
            [
                'country_id' => $countryId,
                'name' => $refState->name,
                'latitude' => $refState->latitude,
                'longitude' => $refState->longitude,
            ],
        );
    }
}
