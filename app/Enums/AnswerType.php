<?php

namespace App\Enums;

enum AnswerType: string
{
    case YesNo = 'yes_no';
    case SingleSelect = 'single_select';
    case MultipleSelect = 'multiple_select';

    public function label(): string
    {
        return match ($this) {
            self::YesNo => __('admin.yes_no'),
            self::SingleSelect => __('admin.single_select'),
            self::MultipleSelect => __('admin.multiple_select'),
        };
    }
}
