<?php

namespace App\Actions;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\BookingRoomAssignment;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AutoAssignRoomsAction
{
    /**
     * Auto-assign physical rooms to an online booking that has just been confirmed.
     *
     * Algorithm:
     *  1. Skip if booking is admin- or partner-created (they pick rooms manually via the Select Room modal).
     *  2. Skip if the booking already has the required number of assignments (idempotent).
     *  3. Find all active, non-deleted rooms for the property + room type that are not
     *     already assigned to another Confirmed/CheckedIn booking overlapping these dates.
     *  4. Try to pick N rooms with consecutive numeric room numbers.
     *  5. Fall back to N random available rooms if consecutive set cannot be found.
     */
    public function handle(Booking $booking): void
    {
        if (in_array($booking->booking_source, [BookingSource::Admin, BookingSource::Partner], true)) {
            return;
        }

        $needed = $booking->booked_rooms - $booking->roomAssignments()->count();

        if ($needed <= 0) {
            return;
        }

        $occupiedRoomIds = BookingRoomAssignment::query()
            ->select('booking_room_assignments.room_id')
            ->join('bookings', 'booking_room_assignments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
            ->where('bookings.id', '!=', $booking->id)
            ->where('bookings.check_in', '<', $booking->check_out->toDateString())
            ->where('bookings.check_out', '>', $booking->check_in->toDateString())
            ->whereNull('bookings.deleted_at')
            ->pluck('room_id');

        $availableRooms = Room::query()
            ->where('property_id', $booking->property_id)
            ->where('property_room_id', $booking->property_room_id)
            ->where('status', RoomStatus::Active->value)
            ->whereNotIn('id', $occupiedRoomIds)
            ->orderBy('room_number')
            ->get();

        if ($availableRooms->count() < $needed) {
            Log::warning('AutoAssignRoomsAction: insufficient available rooms', [
                'booking_id' => $booking->id,
                'needed' => $needed,
                'available' => $availableRooms->count(),
            ]);

            return;
        }

        $selectedRooms = $this->findConsecutiveRooms($availableRooms, $needed);

        if ($selectedRooms->isEmpty()) {
            $selectedRooms = $availableRooms->shuffle()->take($needed);
        }

        $now = now();

        foreach ($selectedRooms as $room) {
            BookingRoomAssignment::query()->create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'assigned_by' => null,
                'assigned_at' => $now,
            ]);
        }
    }

    /**
     * Find N rooms with consecutive numeric room numbers from the available set.
     * Rooms with non-numeric room numbers are skipped entirely.
     * Returns an empty collection if no consecutive run of length $needed exists.
     *
     * @param  Collection<int, Room>  $availableRooms
     * @return Collection<int, Room>
     */
    private function findConsecutiveRooms(Collection $availableRooms, int $needed): Collection
    {
        $numeric = $availableRooms
            ->filter(fn (Room $room) => ctype_digit($room->room_number))
            ->sortBy(fn (Room $room) => (int) $room->room_number)
            ->values();

        if ($numeric->count() < $needed) {
            return collect();
        }

        $count = $numeric->count();

        for ($i = 0; $i <= $count - $needed; $i++) {
            $candidate = $numeric->slice($i, $needed)->values();
            $isConsecutive = true;

            for ($j = 1; $j < $needed; $j++) {
                if ((int) $candidate[$j]->room_number !== (int) $candidate[$j - 1]->room_number + 1) {
                    $isConsecutive = false;
                    break;
                }
            }

            if ($isConsecutive) {
                return $candidate;
            }
        }

        return collect();
    }
}
