<?php

namespace App\Support;

use App\Models\Country;
use Carbon\Carbon;
use Filament\Facades\Filament;

class UserTimezone
{
    /**
     * Get the current user's timezone (IANA) based on their selected country.
     */
    public static function current(): string
    {
        $user = Filament::auth()->user();

        if (! $user || ! $user->current_country_id) {
            return 'UTC';
        }

        return Country::query()
            ->with('refCountry')
            ->find($user->current_country_id)
            ?->timezone ?: 'UTC';
    }

    /**
     * Get the timezone abbreviation (e.g., IST, EST, GMT) for the current user's timezone.
     */
    public static function abbreviation(): string
    {
        try {
            return Carbon::now(self::current())->format('T');
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    /**
     * Get the timezone abbreviation for any given IANA timezone string.
     */
    public static function abbreviationFor(string $timezone): string
    {
        try {
            return Carbon::now($timezone)->format('T');
        } catch (\Throwable) {
            return 'UTC';
        }
    }
}
