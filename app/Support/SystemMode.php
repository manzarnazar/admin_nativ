<?php

namespace App\Support;

use App\Models\Setting;

class SystemMode
{
    public static function isMulti(): bool
    {
        try {
            return Setting::get('system_mode', 'single') === 'multi';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isSingle(): bool
    {
        return ! self::isMulti();
    }
}
