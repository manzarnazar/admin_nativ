<?php

namespace App\Enums;

enum ReviewRemovalStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('admin.requested'),
            self::Approved => __('admin.removed'),
            self::Rejected => __('admin.rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'info',
            self::Approved => 'danger',
            self::Rejected => 'warning',
        };
    }
}
