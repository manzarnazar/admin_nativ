<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomInventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'property_room_id' => PropertyRoom::factory(),
            'date' => now()->toDateString(),
            'total_rooms' => 5,
            'booked_rooms' => 0,
            'locked_rooms' => 0,
        ];
    }
}
