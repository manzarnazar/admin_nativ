<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\Partner;
use App\Models\PropertyWallet;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_wallet_id' => PropertyWallet::factory(),
            'partner_id' => Partner::factory(),
            'amount' => 50,
            'currency_code' => 'USD',
            'bank_account_holder' => fake()->name(),
            'bank_name' => fake()->company(),
            'bank_account_number' => fake()->numerify('##########'),
            'bank_code' => fake()->swiftBicNumber(),
            'status' => WithdrawalStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WithdrawalStatus::Approved,
            'processed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WithdrawalStatus::Rejected,
            'processed_at' => now(),
        ]);
    }
}
