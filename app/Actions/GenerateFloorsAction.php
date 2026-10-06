<?php

namespace App\Actions;

use App\Models\Floor;
use App\Models\Property;
use Illuminate\Database\Eloquent\Collection;

class GenerateFloorsAction
{
    /** Ordinal names for floors 1–20; beyond that we fall back to "Floor N". */
    private const ORDINALS = [
        1 => 'First',
        2 => 'Second',
        3 => 'Third',
        4 => 'Fourth',
        5 => 'Fifth',
        6 => 'Sixth',
        7 => 'Seventh',
        8 => 'Eighth',
        9 => 'Ninth',
        10 => 'Tenth',
        11 => 'Eleventh',
        12 => 'Twelfth',
        13 => 'Thirteenth',
        14 => 'Fourteenth',
        15 => 'Fifteenth',
        16 => 'Sixteenth',
        17 => 'Seventeenth',
        18 => 'Eighteenth',
        19 => 'Nineteenth',
        20 => 'Twentieth',
    ];

    /**
     * Returns floors that would be removed by reducing to $newCount but currently have rooms.
     * An empty collection means the reduction is safe to proceed.
     *
     * @return Collection<int, Floor>
     */
    public function getFloorsBlockingReduction(Property $property, int $newCount): Collection
    {
        $allFloors = Floor::query()
            ->where('property_id', $property->id)
            ->orderBy('sort_order')
            ->get();

        if ($newCount >= $allFloors->count()) {
            return new Collection;
        }

        return $allFloors
            ->slice($newCount)
            ->filter(fn (Floor $floor) => $floor->rooms()->exists())
            ->values();
    }

    public function handle(Property $property): void
    {
        $totalFloors = $property->total_floors;

        if (! $totalFloors || $totalFloors < 1) {
            return;
        }

        $allFloors = Floor::query()
            ->where('property_id', $property->id)
            ->orderBy('sort_order')
            ->get();

        $existingCount = $allFloors->count();

        if ($existingCount > $totalFloors) {
            // Delete empty excess floors (caller must pre-validate via getFloorsBlockingReduction)
            $allFloors->slice($totalFloors)->each->delete();

            return;
        }

        if ($existingCount >= $totalFloors) {
            return;
        }

        for ($i = $existingCount; $i < $totalFloors; $i++) {
            Floor::query()->create([
                'property_id' => $property->id,
                'name' => $this->floorName($i),
                'sort_order' => $i,
            ]);
        }
    }

    private function floorName(int $index): string
    {
        if ($index === 0) {
            return 'Ground Floor';
        }

        return isset(self::ORDINALS[$index])
            ? self::ORDINALS[$index].' Floor'
            : 'Floor '.$index;
    }
}
