<?php

namespace App\Enums;

enum BlogCategoryStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('admin.draft'),
            self::Published => __('admin.published'),
        };
    }
}
