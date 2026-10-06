<?php

namespace App\Enums;

enum MarketingMessageType: string
{
    case Push = 'push';
    case Email = 'email';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Push => 'Push Notification',
            self::Email => 'Email',
            self::Both => 'Both',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Push => 'info',
            self::Email => 'warning',
            self::Both => 'primary',
        };
    }
}
