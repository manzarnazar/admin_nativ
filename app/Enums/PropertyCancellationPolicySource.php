<?php

namespace App\Enums;

enum PropertyCancellationPolicySource: string
{
    case AdminDefault = 'admin_default';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::AdminDefault => __('admin.admin_default_policy'),
            self::Custom => __('admin.custom_policy'),
        };
    }
}
