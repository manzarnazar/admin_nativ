<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::rememberForever("settings.{$key}", function () use ($key, $default) {
                if (! Schema::hasTable('settings')) {
                    return $default;
                }

                $setting = static::query()->where('key', $key)->first();

                return $setting?->value ?? $default;
            });
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        Cache::forget("settings.{$key}");
    }
}
