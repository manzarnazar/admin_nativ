<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NearbyPlace extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'city_id',
        'nearby_place_category_id',
        'google_place_id',
        'name',
        'latitude',
        'longitude',
        'address',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            // Not a real column — populated only when a query computes it via
            // Geo::haversineExpression() and aliases it AS distance_km. The cast
            // still applies to whatever ends up in this attribute after hydration.
            'distance_km' => 'decimal:2',
            'rating' => 'decimal:1',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function nearbyPlaceCategory(): BelongsTo
    {
        return $this->belongsTo(NearbyPlaceCategory::class);
    }
}
