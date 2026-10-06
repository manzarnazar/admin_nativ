<?php

namespace App\Models;

use App\Enums\CityStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ref_city_id',
        'country_id',
        'state_id',
        'name',
        'slug',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CityStatus::class,
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function refCity(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'ref_city_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function nearbyPlaces(): HasMany
    {
        return $this->hasMany(NearbyPlace::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'ref_city_id', 'ref_city_id');
    }

    public function scopeForCountry(Builder $query, int $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CityStatus::Active);
    }
}
