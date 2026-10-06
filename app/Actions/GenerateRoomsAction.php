<?php

namespace App\Actions;

use App\Models\Floor;
use App\Models\PropertyRoom;
use App\Models\Room;

class GenerateRoomsAction
{
    /**
     * @param  array{floor_id: int, property_room_id: int, count: int, start_number: int}  $data
     * @return array{created: int, skipped: list<string>}
     */
    public function handle(array $data): array
    {
        $floor = Floor::query()->findOrFail($data['floor_id']);
        $propertyRoom = PropertyRoom::query()->findOrFail($data['property_room_id']);

        $created = 0;
        $skipped = [];

        for ($i = 0; $i < $data['count']; $i++) {
            $roomNumber = (string) ($data['start_number'] + $i);

            // Skip if an active (non-deleted) room with this number already exists.
            $activeExists = Room::query()
                ->where('property_id', $floor->property_id)
                ->where('room_number', $roomNumber)
                ->exists();

            if ($activeExists) {
                $skipped[] = $roomNumber;

                continue;
            }

            // Restore a soft-deleted room instead of inserting a new row, so that
            // historical booking_room_assignments referencing the same room ID stay valid.
            $deletedRoom = Room::query()
                ->withTrashed()
                ->where('property_id', $floor->property_id)
                ->where('room_number', $roomNumber)
                ->whereNotNull('deleted_at')
                ->first();

            if ($deletedRoom) {
                $deletedRoom->restore();
                $deletedRoom->update([
                    'floor_id' => $floor->id,
                    'property_room_id' => $propertyRoom->id,
                    'status' => 'active',
                ]);
            } else {
                Room::query()->create([
                    'property_id' => $floor->property_id,
                    'floor_id' => $floor->id,
                    'property_room_id' => $propertyRoom->id,
                    'room_number' => $roomNumber,
                    'status' => 'active',
                ]);
            }

            $created++;
        }

        if ($created > 0) {
            $this->syncTotalRooms($propertyRoom);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function syncTotalRooms(PropertyRoom $propertyRoom): void
    {
        $activeCount = Room::query()
            ->where('property_room_id', $propertyRoom->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        $propertyRoom->update(['total_rooms' => $activeCount]);
    }
}
