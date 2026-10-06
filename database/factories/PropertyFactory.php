<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Enums\PropertyVerificationStatus;
use App\Models\Country;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->company().' Hotel',
            'status' => PropertyStatus::Active,
            'verification_status' => PropertyVerificationStatus::Approved,
            'completed_step' => 8,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Test suspension',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Inactive,
        ]);
    }

    public function withBankDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_account_holder' => fake()->name(),
            'bank_name' => fake()->company(),
            'bank_account_number' => fake()->numerify('##########'),
            'bank_code' => fake()->swiftBicNumber(),
        ]);
    }
}
