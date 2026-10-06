<?php

namespace App\Enums;

enum MarketingMessageStatus: string
{
    case Processing = 'processing';
    case Scheduled = 'scheduled';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Processing => __('admin.processing'),
            self::Scheduled => __('admin.scheduled'),
            self::Sent => __('admin.sent'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Processing => 'info',
            self::Scheduled => 'warning',
            self::Sent => 'success',
        };
    }
}
