<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => __('admin.male'),
            self::Female => __('admin.female'),
            self::Other => __('admin.other'),
        };
    }
}
