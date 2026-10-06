<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roomTypes = [
            [
                'name' => 'Standard Room',
                'bed_type' => '1 Queen Bed',
                'max_guests' => 2,
                'description' => 'A comfortable and well-appointed standard room with modern amenities, perfect for solo travelers or couples. Features a queen-size bed, work desk, and en-suite bathroom.',
            ],
            [
                'name' => 'Deluxe Room',
                'bed_type' => '1 King Bed',
                'max_guests' => 2,
                'description' => 'Spacious deluxe room with premium furnishings and city views. Enjoy a king-size bed, sitting area, and luxurious bathroom amenities for a truly relaxing stay.',
            ],
            [
                'name' => 'Family Suite',
                'bed_type' => '2 Queen Beds',
                'max_guests' => 4,
                'description' => 'A generously sized family suite designed for comfort and convenience. Features two queen beds, a separate living area, and kid-friendly amenities to make your family vacation memorable.',
            ],
        ];

        $facilityIds = Facility::query()
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        foreach ($roomTypes as $data) {
            $roomType = RoomType::query()->create(array_merge($data, ['status' => 'active']));

            if (! empty($facilityIds)) {
                $randomFacilities = collect($facilityIds)->random(min(count($facilityIds), rand(3, 6)))->toArray();
                $roomType->facilities()->sync($randomFacilities);
            }
        }
    }
}
