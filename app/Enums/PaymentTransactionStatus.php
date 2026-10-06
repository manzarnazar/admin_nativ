<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Flagged = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('admin.pending'),
            self::Processing => __('admin.processing'),
            self::Success => __('admin.success'),
            self::Failed => __('admin.failed'),
            self::Refunded => __('admin.refunded'),
            self::Cancelled => __('admin.cancelled'),
            self::Expired => __('admin.expired'),
            self::Flagged => __('admin.flagged'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
            self::Cancelled => 'gray',
            self::Expired => 'gray',
            self::Flagged => 'danger',
        };
    }
}
