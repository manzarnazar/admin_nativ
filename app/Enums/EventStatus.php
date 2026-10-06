<?php

namespace App\Enums;

enum EventStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('admin.active_visible'),
            self::Inactive => __('admin.inactive_hidden'),
        };
    }
}
