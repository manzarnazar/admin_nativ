<?php

namespace Database\Seeders;

use App\Models\NearbyPlaceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class NearbyPlaceCategorySeeder extends Seeder
{
    /**
     * Seed the default nearby place categories.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Gas Station',
                'icon' => 'nearby-place-categories/gas-station.svg',
                'sort_order' => 1,
                'google_place_type' => 'gas_station',
                'osm_place_type' => 'amenity=fuel',
                'radius' => 5,
                'total_places' => 10,
            ],
            [
                'name' => 'Hospital',
                'icon' => 'nearby-place-categories/hospital.svg',
                'sort_order' => 2,
                'google_place_type' => 'hospital',
                'osm_place_type' => 'amenity=hospital',
                'radius' => 10,
                'total_places' => 10,
            ],
            [
                'name' => 'Shopping Mall',
                'icon' => 'nearby-place-categories/shopping-mall.svg',
                'sort_order' => 3,
                'google_place_type' => 'shopping_mall',
                'osm_place_type' => 'shop=mall',
                'radius' => 15,
                'total_places' => 10,
            ],
            [
                'name' => 'Restaurant',
                'icon' => 'nearby-place-categories/restaurant.svg',
                'sort_order' => 4,
                'google_place_type' => 'restaurant',
                'osm_place_type' => 'amenity=restaurant',
                'radius' => 5,
                'total_places' => 10,
            ],
            [
                'name' => 'Airport',
                'icon' => 'nearby-place-categories/airport.svg',
                'sort_order' => 5,
                'google_place_type' => 'airport',
                'osm_place_type' => 'aeroway=aerodrome',
                'radius' => 50,
                'total_places' => 5,
            ],
        ];

        $this->copyIconsToStorage();

        foreach ($categories as $category) {
            NearbyPlaceCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                [
                    'icon' => $category['icon'],
                    'sort_order' => $category['sort_order'],
                    'google_place_type' => $category['google_place_type'],
                    'osm_place_type' => $category['osm_place_type'],
                    'radius' => $category['radius'],
                    'total_places' => $category['total_places'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function copyIconsToStorage(): void
    {
        $icons = ['gas-station', 'hospital', 'shopping-mall', 'restaurant', 'airport'];

        foreach ($icons as $icon) {
            $sourcePath = resource_path("svg/{$icon}.svg");
            $destPath = "nearby-place-categories/{$icon}.svg";

            if (file_exists($sourcePath) && ! Storage::disk('public')->exists($destPath)) {
                Storage::disk('public')->put($destPath, file_get_contents($sourcePath));
            }
        }
    }
}
