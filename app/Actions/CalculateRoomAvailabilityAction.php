<?php

namespace App\Actions;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CalculateRoomAvailabilityAction
{
    /**
     * Bulk, set-based per-property room-availability calculation for a stay.
     *
     * Returns a query builder — one row per property_id that has at least one
     * room type — exposing `total_available_rooms`, `greedy_capacity`, and
     * `single_room_type_available`. Callers join/filter on this rather than
     * looping per property (see PropertyService::getProperties()).
     *
     * @param  EloquentBuilder|QueryBuilder  $candidatePropertyIds  Subquery selecting `properties.id`, scoped by whatever filters the caller already applied.
     */
    public function handle(
        EloquentBuilder|QueryBuilder $candidatePropertyIds,
        string $checkIn,
        string $checkOut,
        int $rooms,
    ): QueryBuilder {
        // Stage 1: rooms used (booked+locked) per property_room, worst night in
        // range. No row for a night = 0 used that night (fully available) —
        // matches PropertyService::getProperties()'s date-availability comment
        // and BookingInventoryService::getAvailableRooms()'s semantics.
        $usedPerRoom = DB::table('room_inventory')
            ->select('property_room_id', DB::raw('MAX(booked_rooms + locked_rooms) as max_used'))
            // Filter by the denormalized property_id column so this hits the
            // existing (property_id, date) index instead of a full scan.
            ->whereIn('property_id', clone $candidatePropertyIds)
            ->where('date', '>=', $checkIn)
            ->where('date', '<', $checkOut)
            ->groupBy('property_room_id');

        $availableByRoomType = DB::table('property_rooms as pr')
            ->join('room_types as rt', function ($join) {
                $join->on('rt.id', '=', 'pr.room_type_id')->whereNull('rt.deleted_at');
            })
            ->leftJoinSub($usedPerRoom, 'used', 'used.property_room_id', '=', 'pr.id')
            ->whereNull('pr.deleted_at')
            ->where('pr.total_rooms', '>', 0)
            ->whereIn('pr.property_id', clone $candidatePropertyIds)
            ->select(['pr.property_id', 'pr.id as property_room_id', 'rt.max_guests'])
            ->selectRaw(
                'CASE WHEN (pr.total_rooms - COALESCE(used.max_used, 0)) < 0 THEN 0 '.
                'ELSE (pr.total_rooms - COALESCE(used.max_used, 0)) END as available_rooms'
            );

        // Stage 2: rooms already claimed by higher-max_guests types before
        // this row, per property (the "greedy" ordering).
        $withRoomsBefore = DB::query()->fromSub($availableByRoomType, 'art')
            ->select('art.property_id', 'art.available_rooms', 'art.max_guests')
            ->selectRaw(
                'COALESCE(SUM(art.available_rooms) OVER ('.
                'PARTITION BY art.property_id '.
                'ORDER BY art.max_guests DESC, art.property_room_id ASC '.
                'ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING'.
                '), 0) as rooms_before'
            );

        // Stage 3: rooms actually taken from this type — partial credit on the
        // crossing type. Plain CASE WHEN (portable across MySQL/SQLite), not
        // GREATEST()/LEAST().
        $withRoomsTaken = DB::query()->fromSub($withRoomsBefore, 'g')
            ->select('g.property_id', 'g.available_rooms', 'g.max_guests')
            ->selectRaw(
                "CASE WHEN ({$rooms} - g.rooms_before) <= 0 THEN 0 ".
                "WHEN g.available_rooms < ({$rooms} - g.rooms_before) THEN g.available_rooms ".
                "ELSE ({$rooms} - g.rooms_before) END as rooms_taken"
            );

        // Stage 4: final per-property aggregate.
        return DB::query()->fromSub($withRoomsTaken, 'c')
            ->select('c.property_id')
            ->selectRaw('SUM(c.available_rooms) as total_available_rooms')
            ->selectRaw('SUM(c.rooms_taken * c.max_guests) as greedy_capacity')
            ->selectRaw("MAX(CASE WHEN c.available_rooms >= {$rooms} THEN 1 ELSE 0 END) as single_room_type_available")
            ->groupBy('c.property_id');
    }
}
