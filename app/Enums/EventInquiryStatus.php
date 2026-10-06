<?php

namespace App\Enums;

enum EventInquiryStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Contacted => 'Contacted',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Contacted => 'info',
            self::Closed => 'gray',
        };
    }
}
