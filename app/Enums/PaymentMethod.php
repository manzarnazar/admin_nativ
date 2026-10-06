<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Upi = 'upi';
    case PayOnline = 'pay_online';
    case PayAtProperty = 'pay_at_property';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('admin.cash'),
            self::Upi => __('admin.upi'),
            self::PayOnline => __('admin.pay_online'),
            self::PayAtProperty => __('admin.pay_at_property'),
        };
    }
}
