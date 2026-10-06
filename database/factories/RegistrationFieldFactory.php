<?php

namespace Database\Factories;

use App\Enums\RegistrationFieldScope;
use App\Enums\RegistrationFieldType;
use App\Enums\Status;
use App\Models\Country;
use App\Models\RegistrationField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationField>
 */
class RegistrationFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scope' => RegistrationFieldScope::Partner,
            'country_id' => Country::factory(),
            'property_type_id' => null,
            'name' => fake()->words(2, true),
            'field_type' => RegistrationFieldType::TextField,
            'is_mandatory' => true,
            'status' => Status::Active,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Inactive,
        ]);
    }
}
