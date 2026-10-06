<?php

namespace App\Enums;

enum CancellationInitiator: string
{
    case Customer = 'customer';
    case Partner = 'partner';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => __('admin.customer'),
            self::Partner => __('admin.partner'),
            self::Admin => __('admin.admin'),
        };
    }
}
