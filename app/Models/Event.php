<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'title',
        'slug',
        'description',
        'image_path',
        'features',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'features' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = self::generateUniqueSlug($event->title);
            }
        });
    }

    private static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (self::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
