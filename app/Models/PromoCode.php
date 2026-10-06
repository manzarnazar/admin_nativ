<?php

namespace App\Models;

use App\Enums\CustomerSegment;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoDiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'title',
        'description',
        'is_active',
        'is_auto_apply',
        'discount_type',
        'discount_value',
        'max_discount_cap',
        'min_booking_amount',
        'is_first_booking_only',
        'start_date',
        'end_date',
        'usage_limit',
        'used_count',
        'customer_segment',
        'country_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_auto_apply' => 'boolean',
            'is_first_booking_only' => 'boolean',
            'discount_type' => PromoDiscountType::class,
            'discount_value' => 'float',
            'max_discount_cap' => 'float',
            'min_booking_amount' => 'float',
            'start_date' => 'date',
            'end_date' => 'date',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'customer_segment' => CustomerSegment::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'promo_code_cities');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getStatusAttribute(): PromoCodeStatus
    {
        if (! $this->is_active) {
            return PromoCodeStatus::Inactive;
        }

        if ($this->end_date->lt(today())) {
            return PromoCodeStatus::Expired;
        }

        if ($this->start_date->gt(today())) {
            return PromoCodeStatus::Scheduled;
        }

        return PromoCodeStatus::Active;
    }

    public function scopeForCountry(Builder $query, int $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }
}
