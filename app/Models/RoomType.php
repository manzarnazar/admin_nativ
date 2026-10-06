<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use App\Enums\RoomTypeStatus;
use App\Scopes\PartnerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RoomType extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'partner_id',
        'name',
        'slug',
        'bed_type',
        'max_guests',
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'schema_markup',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomTypeStatus::class,
            'max_guests' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new PartnerScope);

        static::creating(function (RoomType $roomType) {
            if (empty($roomType->slug)) {
                $roomType->slug = self::generateUniqueSlug($roomType->name);
            }
        });

        static::updating(function (RoomType $roomType) {
            if ($roomType->isDirty('name') && ! $roomType->isDirty('slug')) {
                $roomType->slug = self::generateUniqueSlug($roomType->name, $roomType->id);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Room type was {$eventName}");
    }

    private static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'room-type';
        $slug = $base;
        $counter = 2;

        while (self::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomTypeImage::class)->orderBy('sort_order');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'room_type_facility')
            ->where('facilities.status', FacilityStatus::Active);
    }
}
