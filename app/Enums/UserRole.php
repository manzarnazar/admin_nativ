<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Partner = 'partner';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('admin.admin'),
            self::Partner => __('admin.partner'),
            self::Staff => __('admin.staff'),
            self::Customer => __('admin.customer'),
        };
    }
}
