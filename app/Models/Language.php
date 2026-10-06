<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'image',
        'is_rtl',
        'status',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_rtl' => 'boolean',
            'status' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Clear locale caches whenever a language is created, updated, or deleted.
        // Cache::forget() removes a specific key from the cache immediately,
        // so the next request will re-query the DB and store fresh data.
        // Business logic (default toggling, delete guards) lives in LanguageService.
        static::saved(function () {
            Cache::forget('default_locale');
            Cache::forget('active_locales');
        });

        static::deleted(function () {
            Cache::forget('default_locale');
            Cache::forget('active_locales');
        });
    }
}
