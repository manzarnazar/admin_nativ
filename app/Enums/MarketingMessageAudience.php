<?php

namespace App\Enums;

enum MarketingMessageAudience: string
{
    case All = 'all';
    case CityBased = 'city_based';
    case Partners = 'partners';
    case AllUsers = 'all_users';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Customers',
            self::CityBased => 'City Based',
            self::Partners => 'Partners',
            self::AllUsers => 'All Users',
        };
    }
}
