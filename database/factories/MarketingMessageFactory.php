<?php

namespace Database\Factories;

use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageStatus;
use App\Enums\MarketingMessageType;
use App\Models\Country;
use App\Models\MarketingMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingMessage>
 */
class MarketingMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'type' => MarketingMessageType::Push,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => MarketingMessageAudience::All,
            'status' => MarketingMessageStatus::Scheduled,
            'created_by' => User::factory()->admin(),
        ];
    }
}
