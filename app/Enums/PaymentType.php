<?php

namespace App\Enums;

enum PaymentType: string
{
    case Full = 'full';
    case Partial = 'partial';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Full => __('admin.full'),
            self::Partial => __('admin.partial'),
            self::Refund => __('admin.refund'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Full => 'success',
            self::Partial => 'warning',
            self::Refund => 'danger',
        };
    }
}
