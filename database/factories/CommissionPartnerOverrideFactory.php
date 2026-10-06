<?php

namespace Database\Factories;

use App\Models\CommissionPartnerOverride;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionPartnerOverride>
 */
class CommissionPartnerOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'partner_id' => Partner::factory(),
            'property_type_id' => PropertyType::factory(),
            'rate' => 5,
            'description' => 'Negotiated rate',
        ];
    }
}
