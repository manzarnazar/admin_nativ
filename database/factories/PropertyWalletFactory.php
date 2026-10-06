<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyWallet>
 */
class PropertyWalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'balance' => 0,
            'currency_code' => 'USD',
        ];
    }
}
