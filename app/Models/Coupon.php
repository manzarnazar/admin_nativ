<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'user_id',
        'type',
        'value',
        'is_used',
        'used_at',
        'expires_at',
        'source',
        'is_first_booking_only',
        'min_booking_amount',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_first_booking_only' => 'boolean',
            'value' => 'float',
            'min_booking_amount' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'coupon_id');
    }

    public function isValid(): bool
    {
        if ($this->is_used) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
