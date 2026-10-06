<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SortByRule: string implements HasLabel
{
    case Newest = 'newest';
    case HighestRating = 'highest_rating';
    case PriceLowToHigh = 'price_low_high';
    case PriceHighToLow = 'price_high_low';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Newest => 'Newest',
            self::HighestRating => 'Highest Rating',
            self::PriceLowToHigh => 'Price: Low to High',
            self::PriceHighToLow => 'Price: High to Low',
        };
    }
}
