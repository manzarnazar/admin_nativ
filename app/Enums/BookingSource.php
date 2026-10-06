<?php

namespace App\Enums;

enum BookingSource: string
{
    case Application = 'application';
    case Website = 'website';
    case Admin = 'admin';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Application => __('admin.application'),
            self::Website => __('admin.website'),
            self::Admin => __('admin.admin'),
            self::Partner => __('admin.partner'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Application => 'success',
            self::Website => 'info',
            self::Admin => 'gray',
            self::Partner => 'warning',
        };
    }
}
