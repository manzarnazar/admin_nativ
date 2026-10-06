<?php

namespace App\Filament\Partner\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Bookings = 'Bookings';
    case RoomManagement = 'Room Management';
    case ReviewMonitoring = 'Review Monitoring';
    case WalletManagement = 'Wallet Management';
    case PropertyManagement = 'Property Management';
    case CancellationPolicy = 'Cancellation Policy';
    case LocationManagement = 'Location Management';
    case Settings = 'Settings';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PropertyManagement => __('admin.property_management'),
            self::Bookings => __('admin.bookings'),
            self::RoomManagement => __('admin.room_management'),
            self::ReviewMonitoring => __('admin.review_monitoring'),
            self::WalletManagement => __('admin.wallet_management'),
            self::CancellationPolicy => __('admin.cancellation_policy'),
            self::LocationManagement => __('admin.location_management'),
            self::Settings => __('admin.settings'),
        };
    }
}
