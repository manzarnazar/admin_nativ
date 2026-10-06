<?php

namespace App\Enums;

enum CustomerSegment: string
{
    case New = 'new';
    case Returning = 'returning';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::New => __('admin.new'),
            self::Returning => __('admin.returning'),
            self::All => __('admin.all_customers'),
        };
    }
}
