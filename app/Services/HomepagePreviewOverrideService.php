<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class HomepagePreviewOverrideService
{
    private const TTL_MINUTES = 5;

    /**
     * Remember which country (or global) an admin's homepage Live Preview should
     * resolve to, keyed by their own request IP — the same IP the embedded frontend
     * page uses when it calls the public sections API. This lets the preview override
     * IP-based country resolution without the frontend needing to pass anything new.
     */
    public function remember(string $ip, ?int $countryId, bool $forceGlobal): void
    {
        Cache::put($this->cacheKey($ip), [
            'country_id' => $countryId,
            'force_global' => $forceGlobal,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * @return array{country_id: ?int, force_global: bool}|null
     */
    public function resolve(string $ip): ?array
    {
        return Cache::get($this->cacheKey($ip));
    }

    /**
     * Clear an override early, e.g. when the admin closes the Live Preview modal,
     * instead of leaving it to expire on its own after the TTL.
     */
    public function forget(string $ip): void
    {
        Cache::forget($this->cacheKey($ip));
    }

    private function cacheKey(string $ip): string
    {
        return 'homepage_preview_override:'.$ip;
    }
}
