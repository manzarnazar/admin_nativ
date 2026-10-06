<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'floor_id',
        'property_room_id',
        'room_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function propertyRoom(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingRoomAssignment::class);
    }

    public function isActive(): bool
    {
        return $this->status === RoomStatus::Active;
    }
}
