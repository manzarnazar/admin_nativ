<?php

namespace App\Enums;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('admin.active'),
            self::Inactive => __('admin.inactive'),
        };
    }
}
