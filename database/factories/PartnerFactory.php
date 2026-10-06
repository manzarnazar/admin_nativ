<?php

namespace Database\Factories;

use App\Enums\PartnerVerificationStatus;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->partner(),
            'verification_status' => PartnerVerificationStatus::Approved,
            'verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => PartnerVerificationStatus::Pending,
            'verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => PartnerVerificationStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
