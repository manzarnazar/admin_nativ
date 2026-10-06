<?php

namespace App\Enums;

enum PromoCodeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Scheduled = 'scheduled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('admin.active'),
            self::Inactive => __('admin.inactive'),
            self::Scheduled => __('admin.scheduled'),
            self::Expired => __('admin.expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => '#20B364',
            self::Inactive => '#6B7280',
            self::Scheduled => '#2196F3',
            self::Expired => '#D63031',
        };
    }

    public function backgroundColor(): string
    {
        return match ($this) {
            self::Active => '#E5FAEF',
            self::Inactive => '#F3F4F6',
            self::Scheduled => '#E7F4FE',
            self::Expired => '#FBEAEA',
        };
    }
}
