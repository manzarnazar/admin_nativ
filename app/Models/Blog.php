<?php

namespace App\Models;

use App\Enums\BlogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Blog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'blog_category_id',
        'created_by',
        'title',
        'slug',
        'short_description',
        'content',
        'cover_image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'schema_markup',
        'status',
        'published_at',
        'read_time_minutes',
    ];

    protected function casts(): array
    {
        return [
            'status' => BlogStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Free up the slug on soft delete so it can be reused by a new blog.
     */
    protected static function booted(): void
    {
        static::deleting(function (Blog $blog): void {
            if ($blog->isForceDeleting()) {
                return;
            }

            $blog->slug = Str::limit($blog->slug, 200, '').'-deleted-'.$blog->id;
            $blog->saveQuietly();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BlogStatus::Published);
    }
}
