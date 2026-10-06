<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilityCategory;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Seed default facility categories and facilities.
     *
     * Icons are bare filenames served from public/assets/facilities/ (see
     * Facility::getIconUrl() / FacilityCategory::getIconUrl()) — a handful of
     * facilities have no icon (Phosphor's bundled set has no good match for
     * them, e.g. Mirror, Mini Fridge) and are left blank on purpose.
     */
    public function run(): void
    {
        $data = [
            'Room Amenities' => [
                'icon' => 'bed.svg',
                'facilities' => [
                    'Smart TV' => 'television.svg',
                    'Air Conditioning' => 'snowflake.svg',
                    'Mini Fridge' => 'mini-fridge.svg',
                    'Coffee Maker' => 'coffee.svg',
                    'Safe Deposit Box' => 'vault.svg',
                    'Iron & Ironing Board' => 't-shirt.svg',
                    'Wardrobe' => 'coat-hanger.svg',
                    'Work Desk' => 'desk.svg',
                ],
            ],
            'Bathroom' => [
                'icon' => 'shower.svg',
                'facilities' => [
                    'Hot Water' => 'drop.svg',
                    'Bathrobes' => 't-shirt.svg',
                    'Bathtub' => 'bathtub.svg',
                    'Toiletries' => 'sparkle.svg',
                    'Towels' => 'towel.svg',
                    'Slippers' => 'footprints.svg',
                    'Hair Dryer' => 'hair-dryer.svg',
                    'Mirror' => 'frame-corners.svg',
                    'Rain Shower' => 'shower.svg',
                ],
            ],
            'Safety & Accessibility' => [
                'icon' => 'shield-check.svg',
                'facilities' => [
                    'CCTV Surveillance' => 'camera.svg',
                    'Smoke Detectors' => 'alarm.svg',
                    'Fire Safety System' => 'fire-extinguisher.svg',
                    'First Aid Kit' => 'first-aid-kit.svg',
                    'Wheelchair Access' => 'wheelchair.svg',
                    'Elevator Access' => 'elevator.svg',
                    'Secure Keycard Entry' => 'keyhole.svg',
                ],
            ],
            'Food & Dining' => [
                'icon' => 'fork-knife.svg',
                'facilities' => [
                    'Restaurant' => 'fork-knife.svg',
                    'Bakery' => 'bread.svg',
                    'Breakfast Buffet' => 'coffee-bean.svg',
                    'Bar & Lounge' => 'wine.svg',
                    'Café' => 'coffee.svg',
                    'Poolside Bar' => 'martini.svg',
                    'Room Dining' => 'tray.svg',
                    'Kids Menu' => 'baby.svg',
                    'Room Service' => 'bell-ringing.svg',
                ],
            ],
            'Wellness & Recreation' => [
                'icon' => 'swimming-pool.svg',
                'facilities' => [
                    'Swimming Pool' => 'swimming-pool.svg',
                    'Sauna' => 'thermometer-hot.svg',
                    'Spa' => 'flower-lotus.svg',
                    'Steam Room' => 'cloud-fog.svg',
                    'Fitness Center' => 'barbell.svg',
                    'Yoga Room' => 'person-simple-tai-chi.svg',
                    'Jacuzzi' => 'bathtub.svg',
                    'Meditation Room' => 'moon.svg',
                ],
            ],
            'Business & Work Facilities' => [
                'icon' => 'presentation.svg',
                'facilities' => [
                    'Conference Hall' => 'presentation.svg',
                    'Wifi' => 'wifi-high.svg',
                    'Printing Service' => 'printer.svg',
                ],
            ],
            'Transportation & Parking' => [
                'icon' => 'car.svg',
                'facilities' => [
                    'Airport Shuttle' => 'van.svg',
                    'Free Parking' => 'garage.svg',
                    'Valet Parking' => 'car-simple.svg',
                    'Car Rental' => 'car.svg',
                    'Bicycle Rental' => 'bicycle.svg',
                    'EV Charging Station' => 'charging-station.svg',
                    'Luggage Transfer' => 'suitcase-rolling.svg',
                ],
            ],
            'Family & Kids Facilities' => [
                'icon' => 'users-three.svg',
                'facilities' => [
                    'Kids Play Area' => 'baby-carriage.svg',
                    'Family Rooms' => 'users-three.svg',
                    'Outdoor Playground' => 'park.svg',
                    'Indoor Games' => 'puzzle-piece.svg',
                    'Kids Pool' => 'swimming-pool.svg',
                ],
            ],
            'Entertainment' => [
                'icon' => 'game-controller.svg',
                'facilities' => [
                    'Gaming Rooms' => 'game-controller.svg',
                    'Library' => 'books.svg',
                    'Sports Court' => 'court-basketball.svg',
                    'Live Room' => 'microphone-stage.svg',
                ],
            ],
        ];

        $categorySortOrder = 1;

        foreach ($data as $categoryName => $categoryData) {
            $category = FacilityCategory::query()->updateOrCreate(
                ['name' => $categoryName],
                [
                    'icon' => $categoryData['icon'],
                    'sort_order' => $categorySortOrder++,
                    'status' => 'active',
                ],
            );

            $facilitySortOrder = 1;

            foreach ($categoryData['facilities'] as $facilityName => $facilityIcon) {
                Facility::query()->updateOrCreate(
                    [
                        'facility_category_id' => $category->id,
                        'name' => $facilityName,
                    ],
                    [
                        'icon' => $facilityIcon,
                        'sort_order' => $facilitySortOrder++,
                        'status' => 'active',
                    ],
                );
            }
        }

        $this->removeStaleFacilities($data);
    }

    /**
     * Full-replace cleanup: soft-deletes any category or facility left over from
     * a previous taxonomy that isn't part of the current $data set — including
     * facilities under a category name that's being kept (e.g. old "Wi-Fi" under
     * "Room Amenities") as well as entire categories no longer present at all
     * (e.g. old "General", "Recreation").
     *
     * @param  array<string, array{icon: string, facilities: array<string, string>}>  $data
     */
    private function removeStaleFacilities(array $data): void
    {
        $keptFacilityNamesByCategory = array_map(
            fn (array $categoryData): array => array_keys($categoryData['facilities']),
            $data,
        );

        Facility::query()
            ->with('category')
            ->get()
            ->each(function (Facility $facility) use ($keptFacilityNamesByCategory): void {
                $categoryName = $facility->category?->name;
                $keptNames = $keptFacilityNamesByCategory[$categoryName] ?? [];

                if (! in_array($facility->name, $keptNames, true)) {
                    $facility->delete();
                }
            });

        FacilityCategory::query()
            ->whereNotIn('name', array_keys($data))
            ->get()
            ->each(fn (FacilityCategory $category) => $category->delete());
    }
}
