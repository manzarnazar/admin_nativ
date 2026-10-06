<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PropertyRoom extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'room_type_id',
        'slug',
        'total_rooms',
        'room_size',
        'base_price_per_night',
    ];

    protected static function booted(): void
    {
        static::creating(function (PropertyRoom $propertyRoom) {
            if (empty($propertyRoom->slug)) {
                $propertyRoom->slug = self::generateSlug($propertyRoom);
            }
        });
    }

    public static function generateSlug(PropertyRoom $propertyRoom): string
    {
        $property = $propertyRoom->property ?? Property::query()->find($propertyRoom->property_id);
        $roomType = $propertyRoom->roomType ?? RoomType::query()->find($propertyRoom->room_type_id);

        $base = Str::slug(($property?->slug ?? 'property').'-'.($roomType?->name ?? 'room'));
        $slug = $base;
        $counter = 2;

        while (self::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'total_rooms' => 'integer',
            'base_price_per_night' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(RoomInventory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function physicalRooms(): HasMany
    {
        return $this->hasMany(Room::class)->orderBy('room_number');
    }

    public function activePhysicalRooms(): HasMany
    {
        return $this->hasMany(Room::class)->where('status', 'active')->orderBy('room_number');
    }
}
