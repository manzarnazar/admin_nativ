<?php

namespace App\Support;

use App\Models\Setting;

class MapProvider
{
    /**
     * The raw configured provider value ('google' or 'openstreetmap').
     * Defaults to 'openstreetmap', matching the seeded install default
     * (see database/seeders/ContactSettingsSeeder.php).
     */
    public static function current(): string
    {
        return Setting::get('map_provider', 'openstreetmap');
    }

    public static function isOsm(): bool
    {
        return self::current() === 'openstreetmap';
    }
}
