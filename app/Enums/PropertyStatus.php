<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('admin.draft'),
            self::Active => __('admin.active'),
            self::Inactive => __('admin.inactive'),
            self::Suspended => __('admin.suspended'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Suspended => 'danger',
        };
    }
}
