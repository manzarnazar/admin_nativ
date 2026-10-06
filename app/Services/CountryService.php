<?php

namespace App\Services;

use App\Enums\SetupTask;
use App\Models\Country;
use App\Models\CountrySetupTask;
use App\Models\PropertyType;
use App\Models\RefCountry;
use App\Models\Tax;
use App\Support\SystemMode;

class CountryService
{
    public function createCountry(int $refCountryId, bool $isActive, array $taxes = []): Country
    {
        $refCountry = RefCountry::findOrFail($refCountryId);

        $country = Country::query()->create([
            'ref_country_id' => $refCountry->id,
            'name' => $refCountry->name,
            'iso_code' => strtolower($refCountry->iso2),
            'phone_code' => $refCountry->phonecode,
            'currency_symbol' => $refCountry->currency_symbol,
            'currency_code' => $refCountry->currency,
            'currency_name' => $refCountry->currency_name,
            'is_active' => $isActive,
        ]);

        // Seed setup tasks for this country
        CountrySetupTask::seedForCountries([$country->id]);

        // Auto-add currency if not already exists
        try {
            app(CurrencyService::class)->addFromRefCountry($refCountry->id);
        } catch (\RuntimeException $e) {
            // Currency already exists, silently continue
        }

        // Create default banner for the new country
        app(BannerService::class)->createDefaultBanner($country);

        // Create taxes if provided
        if (! empty($taxes)) {
            if (SystemMode::isMulti()) {
                $propertyTypeIds = PropertyType::query()
                    ->where('is_active', true)
                    ->whereHas('countries', fn ($q) => $q
                        ->where('country_property_types.country_id', $country->id)
                        ->where('country_property_types.is_enabled', true)
                    )
                    ->pluck('id')
                    ->toArray();
            } else {
                $propertyTypeIds = PropertyType::query()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();
            }

            foreach ($taxes as $taxData) {
                $taxData['country_id'] = $country->id;
                $tax = Tax::query()->create($taxData);

                if (! empty($propertyTypeIds)) {
                    $tax->propertyTypes()->sync($propertyTypeIds);
                }
            }

            CountrySetupTask::markComplete(SetupTask::Taxes, $country->id);
        }

        return $country;
    }

    public function updateCountry(Country $country, array $data): Country
    {
        // If setting as default, remove default from all others first
        if (! empty($data['is_default'])) {
            Country::query()
                ->where('id', '!=', $country->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $country->update([
            'is_active' => $data['is_active'],
            'is_default' => $data['is_default'] ?? false,
        ]);

        return $country;
    }
}
