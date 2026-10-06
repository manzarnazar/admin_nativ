<?php

namespace App\Services;

use App\Enums\InventoryLockStatus;
use App\Models\InventoryLock;
use App\Models\PropertyRoom;
use App\Models\RoomInventory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingInventoryService
{
    /**
     * Check available rooms for a room type across a date range.
     * Returns the minimum available across all dates (bottleneck).
     */
    public function getAvailableRooms(PropertyRoom $propertyRoom, string $checkIn, string $checkOut): int
    {
        $dates = $this->dateRange($checkIn, $checkOut);
        $totalRooms = $propertyRoom->total_rooms;
        $minAvailable = $totalRooms;

        foreach ($dates as $date) {
            $inventory = RoomInventory::query()
                ->where('property_room_id', $propertyRoom->id)
                ->where('date', $date->format('Y-m-d'))
                ->first();

            $available = $inventory
                ? $totalRooms - $inventory->booked_rooms - $inventory->locked_rooms
                : $totalRooms;

            $minAvailable = min($minAvailable, $available);
        }

        return max(0, $minAvailable);
    }

    /**
     * Reserve inventory for a booking (increment booked_rooms).
     * Uses lockForUpdate to prevent race conditions.
     *
     * @throws \RuntimeException If insufficient inventory on any date
     */
    public function reserve(PropertyRoom $propertyRoom, string $checkIn, string $checkOut, int $quantity): void
    {
        foreach ($this->dateRange($checkIn, $checkOut) as $date) {
            $dateStr = $date->format('Y-m-d');

            $inventory = RoomInventory::query()
                ->where('property_room_id', $propertyRoom->id)
                ->where('date', $dateStr)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = RoomInventory::query()->create([
                    'property_id' => $propertyRoom->property_id,
                    'property_room_id' => $propertyRoom->id,
                    'date' => $dateStr,
                    'total_rooms' => $propertyRoom->total_rooms,
                    'booked_rooms' => 0,
                    'locked_rooms' => 0,
                ]);
            }

            $available = $propertyRoom->total_rooms - $inventory->booked_rooms - $inventory->locked_rooms;

            if ($available < $quantity) {
                throw new \RuntimeException(
                    "Insufficient rooms on {$dateStr}. Available: {$available}, Requested: {$quantity}"
                );
            }

            $inventory->increment('booked_rooms', $quantity);
        }
    }

    /**
     * Temporarily lock inventory for checkout (increment locked_rooms).
     *
     * @throws ValidationException
     */
    public function lock(PropertyRoom $propertyRoom, string $checkIn, string $checkOut, int $quantity): void
    {
        foreach ($this->dateRange($checkIn, $checkOut) as $date) {
            $dateStr = $date->format('Y-m-d');

            $inventory = RoomInventory::query()
                ->where('property_room_id', $propertyRoom->id)
                ->where('date', $dateStr)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = RoomInventory::query()->create([
                    'property_id' => $propertyRoom->property_id,
                    'property_room_id' => $propertyRoom->id,
                    'date' => $dateStr,
                    'total_rooms' => $propertyRoom->total_rooms,
                    'booked_rooms' => 0,
                    'locked_rooms' => 0,
                ]);
            }

            $available = $propertyRoom->total_rooms - $inventory->booked_rooms - $inventory->locked_rooms;

            if ($available < $quantity) {
                $message = $available === 0
                    ? 'No rooms are available for the selected dates.'
                    : "Only {$available} room(s) are available for the selected dates.";

                throw ValidationException::withMessages(['rooms' => $message]);
            }

            $inventory->increment('locked_rooms', $quantity);
        }
    }

    /**
     * Release inventory for a booking (decrement booked_rooms).
     */
    public function release(PropertyRoom $propertyRoom, string|Carbon $checkIn, string|Carbon $checkOut, int $quantity): void
    {
        $checkIn = $checkIn instanceof Carbon ? $checkIn->format('Y-m-d') : $checkIn;
        $checkOut = $checkOut instanceof Carbon ? $checkOut->format('Y-m-d') : $checkOut;

        foreach ($this->dateRange($checkIn, $checkOut) as $date) {
            $inventory = RoomInventory::query()
                ->where('property_room_id', $propertyRoom->id)
                ->where('date', $date->format('Y-m-d'))
                ->first();

            if ($inventory && $inventory->booked_rooms >= $quantity) {
                $inventory->decrement('booked_rooms', $quantity);
            }
        }
    }

    /**
     * Convert locked inventory into confirmed booked inventory.
     *
     * @throws ValidationException
     */
    public function convertLockedToBooking(InventoryLock $lock): void
    {
        foreach ($this->dateRange($lock->check_in->toDateString(), $lock->check_out->toDateString()) as $date) {
            $dateStr = $date->format('Y-m-d');

            $inventory = RoomInventory::query()
                ->where('property_room_id', $lock->property_room_id)
                ->where('date', $dateStr)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                throw ValidationException::withMessages([
                    'lock_id' => 'Inventory mismatch detected while confirming the reservation.',
                ]);
            }

            if ($inventory->locked_rooms < $lock->quantity) {
                throw ValidationException::withMessages([
                    'lock_id' => 'Reserved rooms are no longer available for confirmation.',
                ]);
            }

            $inventory->decrement('locked_rooms', $lock->quantity);
            $inventory->increment('booked_rooms', $lock->quantity);
        }
    }

    /**
     * Expire an inventory lock: release locked_rooms and mark the lock as expired.
     * Wrapped in a transaction so the decrement and status update are atomic.
     */
    public function expireLock(InventoryLock $lock): void
    {
        DB::transaction(function () use ($lock) {
            foreach ($this->dateRange($lock->check_in->toDateString(), $lock->check_out->toDateString()) as $date) {
                $inventory = RoomInventory::query()
                    ->where('property_room_id', $lock->property_room_id)
                    ->where('date', $date->format('Y-m-d'))
                    ->lockForUpdate()
                    ->first();

                if ($inventory && $inventory->locked_rooms >= $lock->quantity) {
                    $inventory->decrement('locked_rooms', $lock->quantity);
                }
            }

            $lock->update(['status' => InventoryLockStatus::Expired]);
        });
    }

    /**
     * Date range for inventory: check-in inclusive, check-out exclusive (last night is check-out minus 1 day).
     */
    private function dateRange(string $checkIn, string $checkOut): CarbonPeriod
    {
        return CarbonPeriod::create(
            Carbon::parse($checkIn),
            Carbon::parse($checkOut)->subDay()
        );
    }
}
