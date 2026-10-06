<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Paid => __('admin.paid'),
            self::Unpaid => __('admin.unpaid'),
            self::Partial => __('admin.partial'),
            self::Refunded => __('admin.refunded'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Unpaid => 'warning',
            self::Partial => 'info',
            self::Refunded => 'danger',
        };
    }
}
