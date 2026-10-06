<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NearbyPlaceCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'google_place_type',
        'osm_place_type',
        'radius',
        'total_places',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function sortOrder(): Attribute
    {
        return Attribute::make(
            set: fn ($value): int => (int) ($value ?? 0),
        );
    }

    protected static function booted(): void
    {
        // Free up the unique `name` for reuse by appending the row id on soft delete.
        // Without this, recreating a previously-deleted category trips the unique constraint.
        static::deleting(function (NearbyPlaceCategory $category): void {
            if ($category->isForceDeleting()) {
                return;
            }

            $category->forceFill([
                'name' => $category->name.'-deleted-'.$category->id,
            ])->saveQuietly();
        });
    }

    public function nearbyPlaces(): HasMany
    {
        return $this->hasMany(NearbyPlace::class);
    }
}
