<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyRoom>
 */
class PropertyRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_type_id' => RoomType::factory(),
            'total_rooms' => 5,
            'base_price_per_night' => 100,
        ];
    }
}
