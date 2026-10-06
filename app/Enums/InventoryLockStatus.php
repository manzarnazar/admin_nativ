<?php

namespace App\Enums;

enum InventoryLockStatus: string
{
    case Active = 'active';
    case Converted = 'converted';
    case Expired = 'expired';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('admin.active'),
            self::Converted => __('admin.converted'),
            self::Expired => __('admin.expired'),
            self::Released => __('admin.released'),
        };
    }
}
