<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('admin.active'),
            self::Inactive => __('admin.inactive'),
            self::Suspended => __('admin.suspended'),
            self::Banned => __('admin.banned'),
        };
    }
}
