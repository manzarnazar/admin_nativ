<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => $this->faker->unique()->numberBetween(10000, 99999),
            'user_id' => \App\Models\User::inRandomOrder()->first()?->id ?? 1,
            'property_id' => $this->faker->numberBetween(1, 50),
            'rating' => $this->faker->randomFloat(1, 3.5, 5),
            'review' => $this->faker->paragraph(2),
            'status' => 'approved',
            'is_visible' => true,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
