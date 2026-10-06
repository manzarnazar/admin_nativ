<?php

namespace App\Enums;

enum LoginStatus: string
{
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Success => __('admin.success'),
            self::Failed => __('admin.failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }
}
