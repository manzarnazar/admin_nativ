<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'iso_code' => fake()->unique()->countryCode(),
            'phone_code' => (string) fake()->numberBetween(1, 999),
            'currency_symbol' => '$',
            'currency_code' => fake()->unique()->currencyCode(),
            'currency_name' => 'US Dollar',
            'is_active' => true,
        ];
    }
}
