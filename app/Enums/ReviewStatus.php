<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Published = 'published';
    case Removed = 'removed';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Published => __('admin.published'),
            self::Removed => __('admin.removed'),
            self::Pending => __('admin.pending'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Removed => 'danger',
            self::Pending => 'warning',
        };
    }
}
