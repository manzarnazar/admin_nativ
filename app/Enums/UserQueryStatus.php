<?php

namespace App\Enums;

enum UserQueryStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('admin.pending'),
            self::Reviewed => __('admin.reviewed'),
            self::Resolved => __('admin.resolved'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Reviewed => 'info',
            self::Resolved => 'success',
        };
    }
}
