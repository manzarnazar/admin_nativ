<?php

namespace App\Services;

use App\Models\PropertyRoom;
use App\Models\RoomType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RoomTypeService
{
    public function createRoomType(array $data, array $facilityIds = [], array $images = []): RoomType
    {
        try {
            $roomType = RoomType::query()->create($data);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                throw ValidationException::withMessages([
                    'name' => __('admin.room_type_name_already_exists'),
                ]);
            }

            throw $e;
        }

        if (! empty($facilityIds)) {
            $roomType->facilities()->sync($facilityIds);
        }

        if (! empty($images)) {
            $this->syncImages($roomType, $images);
        }

        return $roomType;
    }

    public function updateRoomType(RoomType $roomType, array $data, array $facilityIds = [], array $images = []): RoomType
    {
        try {
            $roomType->update($data);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                throw ValidationException::withMessages([
                    'name' => __('admin.room_type_name_already_exists'),
                ]);
            }

            throw $e;
        }

        $roomType->facilities()->sync($facilityIds);

        $this->syncImages($roomType, $images);

        return $roomType;
    }

    public function deleteRoomType(RoomType $roomType): void
    {
        if (PropertyRoom::query()->where('room_type_id', $roomType->id)->whereHas('property')->exists()) {
            throw ValidationException::withMessages([
                'room_type' => __('admin.room_type_has_associated_rooms'),
            ]);
        }

        foreach ($roomType->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $roomType->delete();
    }

    private function syncImages(RoomType $roomType, array $images): void
    {
        $existingPaths = $roomType->images()->pluck('image_path')->toArray();

        $incomingPaths = array_values($images);

        $toDelete = array_diff($existingPaths, $incomingPaths);
        if (! empty($toDelete)) {
            $roomType->images()->whereIn('image_path', $toDelete)->delete();
            foreach ($toDelete as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        foreach ($incomingPaths as $index => $path) {
            $roomType->images()->updateOrCreate(
                ['image_path' => $path],
                ['sort_order' => $index + 1]
            );
        }
    }
}
