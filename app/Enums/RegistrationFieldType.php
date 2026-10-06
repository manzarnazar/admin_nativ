<?php

namespace App\Enums;

enum RegistrationFieldType: string
{
    case NumberInput = 'number_input';
    case TextField = 'text_field';
    case TextArea = 'text_area';
    case Checkboxes = 'checkboxes';
    case Date = 'date';
    case Dropdown = 'dropdown';
    case FileUpload = 'file_upload';

    public function label(): string
    {
        return match ($this) {
            self::NumberInput => __('admin.number_input'),
            self::TextField => __('admin.text_field'),
            self::TextArea => __('admin.text_area'),
            self::Checkboxes => __('admin.checkboxes'),
            self::Date => __('admin.date'),
            self::Dropdown => __('admin.dropdown'),
            self::FileUpload => __('admin.file_upload'),
        };
    }
}
