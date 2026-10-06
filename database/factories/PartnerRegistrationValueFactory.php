<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerRegistrationValue;
use App\Models\RegistrationField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerRegistrationValue>
 */
class PartnerRegistrationValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'registration_field_id' => RegistrationField::factory(),
            'value' => [fake()->word()],
        ];
    }
}
