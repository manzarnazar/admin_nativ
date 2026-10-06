<?php

namespace App\Services\Api;

use App\Models\Country;

class CountryService
{
    /**
     * Get all active countries.
     *
     * @return array<string, mixed>
     */
    public function getCountries(): array
    {
        $countries = Country::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return [
            'data' => $countries->map(fn (Country $country) => [
                'id' => $country->id,
                'name' => $country->name,
                'iso_code' => $country->iso_code,
                'phone_code' => $country->phone_code,
                'currency_code' => $country->currency_code,
                'currency_symbol' => $country->currency_symbol,
                'currency_name' => $country->currency_name,
                'flag' => asset('assets/flags/' . strtolower($country->iso_code) . '.svg'),
                'is_default' => $country->is_default,
            ])->toArray(),
        ];
    }
}
