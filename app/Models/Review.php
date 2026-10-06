<?php

namespace App\Models;

use App\Enums\ReviewRemovalStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'booking_id',
        'user_id',
        'property_id',
        'property_room_id',
        'rating',
        'review',
        'status',
        'is_visible',
        'is_featured',
        'featured_order',
        'is_edited',
        'edited_at',
        'removal_requested',
        'removal_status',
        'removal_reason',
        'removal_description',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'is_visible' => 'boolean',
            'is_featured' => 'boolean',
            'is_edited' => 'boolean',
            'removal_requested' => 'boolean',
            'removal_status' => ReviewRemovalStatus::class,
            'edited_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyRoom(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class)->orderBy('sort_order');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get formatted review data for API responses with full image URLs
     */
    public function getFormattedData(): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'review' => $this->review,
            'date' => $this->created_at->format('M d, Y'),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->getFilamentAvatarUrl(),
            ],
            'images' => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->image_path ? (filter_var($img->image_path, FILTER_VALIDATE_URL) ? $img->image_path : asset('storage/'.$img->image_path)) : null,
            ])->toArray(),
        ];
    }
}
