<?php

namespace App\Enums;

enum WalletTransactionReferenceType: string
{
    case BookingRevenue = 'booking_revenue';
    case CancellationRevenue = 'cancellation_revenue';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::BookingRevenue => 'Booking Amount',
            self::CancellationRevenue => 'Refund',
            self::Withdrawal => 'Withdraw',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BookingRevenue => 'info',
            self::CancellationRevenue => 'gray',
            self::Withdrawal => 'danger',
        };
    }
}
