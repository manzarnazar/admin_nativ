<?php

namespace Database\Factories;

use App\Models\CommissionRate;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRate>
 */
class CommissionRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'property_type_id' => null,
            'rate' => 10,
        ];
    }
}
