<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum HomepageSectionType: string implements HasLabel
{
    case CityBased = 'city_based';
    case TopRated = 'top_rated';
    case Popular = 'popular';
    case AllProperty = 'all_property';
    case RecentlyViewed = 'recently_viewed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CityBased => 'City Based',
            self::TopRated => 'Top Rated',
            self::Popular => 'Popular',
            self::AllProperty => 'All Property',
            self::RecentlyViewed => 'Recently Viewed',
        };
    }
}
