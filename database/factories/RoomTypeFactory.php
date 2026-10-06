<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roomTypes = [
            ['name' => 'Standard Room', 'bed_type' => '1 Queen Bed', 'max_guests' => 2],
            ['name' => 'Deluxe Room', 'bed_type' => '1 King Bed', 'max_guests' => 2],
            ['name' => 'Deluxe Ocean Suite', 'bed_type' => '1 King Bed + 1 Sofa Bed', 'max_guests' => 3],
            ['name' => 'Family Suite', 'bed_type' => '2 Queen Beds', 'max_guests' => 4],
            ['name' => 'Executive Suite', 'bed_type' => '1 King Bed + 1 Sofa Bed', 'max_guests' => 3],
            ['name' => 'Presidential Suite', 'bed_type' => '1 King Bed + 2 Single Beds', 'max_guests' => 4],
            ['name' => 'Twin Room', 'bed_type' => '2 Single Beds', 'max_guests' => 2],
            ['name' => 'Superior Room', 'bed_type' => '1 King Bed', 'max_guests' => 2],
        ];

        $type = $this->faker->randomElement($roomTypes);

        return [
            'name' => $type['name'],
            'bed_type' => $type['bed_type'],
            'max_guests' => $type['max_guests'],
            'description' => $this->faker->paragraph(3),
            'status' => 'active',
        ];
    }
}
