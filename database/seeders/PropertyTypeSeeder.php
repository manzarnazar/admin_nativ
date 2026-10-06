<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Seeder;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Seed the default property types.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Hotel', 'icon' => 'Hotel.svg', 'description' => 'Traditional hotel with rooms and amenities.'],
            ['name' => 'Homestay', 'icon' => 'Homestay.svg', 'description' => 'Private home or room shared with the host.'],
            ['name' => 'Villa', 'icon' => 'Villa.svg', 'description' => 'Standalone luxury property with private facilities.'],
            ['name' => 'Apartment', 'icon' => 'Apartment.svg', 'description' => 'Self-contained unit within a residential building.'],
            ['name' => 'Resort', 'icon' => 'Resort.svg', 'description' => 'Full-service property with recreation and dining.'],
        ];

        foreach ($types as $type) {
            PropertyType::query()->updateOrCreate(
                ['name' => $type['name']],
                [
                    'icon' => $type['icon'],
                    'description' => $type['description'],
                    'is_default' => true,
                    'is_active' => false,
                ],
            );
        }
    }
}
