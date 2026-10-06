<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomInventory extends Model
{
    use HasFactory;

    protected $table = 'room_inventory';

    /**
     * Force plain Y-m-d serialization for the date-cast `date` column so it matches
     * across database engines. Without this, Eloquent's default date-cast format
     * ('Y-m-d H:i:s') is truncated to a bare date by MySQL's native DATE column type
     * on write, but SQLite (used in tests) stores the time verbatim — breaking the
     * exact-string date lookups used throughout BookingInventoryService.
     */
    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'property_id',
        'property_room_id',
        'date',
        'total_rooms',
        'booked_rooms',
        'locked_rooms',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_rooms' => 'integer',
            'booked_rooms' => 'integer',
            'locked_rooms' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyRoom(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class);
    }

    public function getAvailableRoomsAttribute(): int
    {
        $totalRooms = $this->propertyRoom?->total_rooms ?? $this->total_rooms;

        return max(0, $totalRooms - $this->booked_rooms - $this->locked_rooms);
    }
}
