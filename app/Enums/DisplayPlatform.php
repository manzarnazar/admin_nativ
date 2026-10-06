<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DisplayPlatform: string implements HasColor, HasIcon, HasLabel
{
    case Web = 'web';
    case App = 'app';
    case Both = 'both';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Web => 'Web',
            self::App => 'App',
            self::Both => 'Both',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Web => 'heroicon-o-computer-desktop',
            self::App => 'heroicon-o-device-phone-mobile',
            self::Both => 'heroicon-o-globe-alt',
        };
    }

    public function getColor(): string|array|null
    {
        return 'primary';
    }
}
