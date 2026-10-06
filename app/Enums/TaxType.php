<?php

namespace App\Enums;

enum TaxType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => __('admin.percentage'),
            self::Fixed => __('admin.fixed'),
        };
    }
}
