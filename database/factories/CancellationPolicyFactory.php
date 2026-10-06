<?php

namespace Database\Factories;

use App\Models\CancellationPolicy;
use App\Models\Country;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancellationPolicy>
 */
class CancellationPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'partner_id' => null,
            'property_type_id' => PropertyType::factory(),
            'cancellation_cutoff_time' => '14:00:00',
            'is_active' => true,
        ];
    }
}
