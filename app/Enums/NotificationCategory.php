<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case BookingUpdates = 'booking_updates';
    case Reminders = 'reminders';
    case Payments = 'payments';
    case RefundUpdates = 'refund_updates';

    public function label(): string
    {
        return match ($this) {
            self::BookingUpdates => 'Booking Confirmation & Changes',
            self::Reminders => 'Check-in & Check-out Reminders',
            self::Payments => 'Payment Confirmations',
            self::RefundUpdates => 'Refund Updates',
        };
    }
}
