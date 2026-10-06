<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('admin.pending'),
            self::PendingPayment => __('admin.pending_payment'),
            self::Confirmed => __('admin.confirmed'),
            self::CheckedIn => __('admin.checked_in'),
            self::Completed => __('admin.completed'),
            self::Cancelled => __('admin.cancelled'),
            self::Expired => __('admin.expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PendingPayment => 'warning',
            self::Confirmed => 'success',
            self::CheckedIn => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
            self::Expired => 'secondary',
        };
    }
}
