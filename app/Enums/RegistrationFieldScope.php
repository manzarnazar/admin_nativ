<?php

namespace App\Enums;

enum RegistrationFieldScope: string
{
    case Property = 'property';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Property => __('admin.property'),
            self::Partner => __('admin.partner'),
        };
    }
}
