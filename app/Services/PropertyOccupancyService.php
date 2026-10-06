<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\PropertyRoom;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PropertyOccupancyService
{
    /**
     * Calculate the room-night occupancy percentage for each given property
     * over the period, based on stay dates (check_in/check_out) overlapping
     * the period rather than booking creation date.
     *
     * @param  Collection<int, Property>  $properties
     * @return array<int, float> Property ID => occupancy percentage (0-100)
     */
    public function calculateOccupancyRates(Collection $properties, Carbon $periodStart, Carbon $periodEnd): array
    {
        if ($properties->isEmpty()) {
            return [];
        }

        $propertyIds = $properties->pluck('id');

        $periodStart = $periodStart->copy()->startOfDay();
        $periodEndExclusive = $periodEnd->copy()->startOfDay()->addDay();
        $nightsInPeriod = max(1, $periodStart->diffInDays($periodEndExclusive));

        $totalRoomsByProperty = PropertyRoom::query()
            ->whereIn('property_id', $propertyIds)
            ->selectRaw('property_id, SUM(total_rooms) as total_rooms')
            ->groupBy('property_id')
            ->pluck('total_rooms', 'property_id');

        $bookings = Booking::query()
            ->whereIn('property_id', $propertyIds)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where('check_in', '<', $periodEndExclusive)
            ->where('check_out', '>', $periodStart)
            ->get(['property_id', 'check_in', 'check_out', 'booked_rooms']);

        $occupiedRoomNightsByProperty = [];

        foreach ($bookings as $booking) {
            $overlapStart = $booking->check_in->max($periodStart);
            $overlapEnd = $booking->check_out->min($periodEndExclusive);
            $nights = $overlapEnd->greaterThan($overlapStart) ? $overlapStart->diffInDays($overlapEnd) : 0;

            $occupiedRoomNightsByProperty[$booking->property_id] = ($occupiedRoomNightsByProperty[$booking->property_id] ?? 0)
                + ($nights * $booking->booked_rooms);
        }

        $rates = [];

        foreach ($propertyIds as $propertyId) {
            $availableRoomNights = ($totalRoomsByProperty[$propertyId] ?? 0) * $nightsInPeriod;

            $rate = $availableRoomNights > 0
                ? (($occupiedRoomNightsByProperty[$propertyId] ?? 0) / $availableRoomNights) * 100
                : 0.0;

            $rates[$propertyId] = min(100.0, round($rate, 0));
        }

        return $rates;
    }
}
