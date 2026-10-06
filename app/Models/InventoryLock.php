<?php

namespace App\Models;

use App\Enums\InventoryLockStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLock extends Model
{
    protected $fillable = [
        'property_id',
        'property_room_id',
        'user_id',
        'booking_id',
        'check_in',
        'check_out',
        'quantity',
        'expires_at',
        'status',
        'platform',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'status' => InventoryLockStatus::class,
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isActive(): bool
    {
        return $this->status === InventoryLockStatus::Active && $this->expires_at->isFuture();
    }
}
