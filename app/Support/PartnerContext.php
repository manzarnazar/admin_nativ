<?php

namespace App\Support;

use App\Models\Partner;
use App\Models\Property;

class PartnerContext
{
    /**
     * Resolve the partner's active country id from session, falling back to their first country.
     */
    public static function currentCountryId(Partner $partner): ?int
    {
        $countries = $partner->countries;

        if ($countries->isEmpty()) {
            return null;
        }

        $sessionCountryId = session('partner_current_country_id');

        if ($sessionCountryId) {
            $found = $countries->firstWhere('id', (int) $sessionCountryId);

            if ($found) {
                return $found->id;
            }
        }

        return $countries->first()->id;
    }

    /**
     * Resolve the partner's active property id within the given country, falling back to the
     * first property in that country. Returns null if the partner has no properties there.
     */
    public static function currentPropertyId(Partner $partner, ?int $countryId): ?int
    {
        if (! $countryId) {
            return null;
        }

        $sessionPropertyId = session('partner_current_branch_id');

        if ($sessionPropertyId && Property::query()
            ->whereKey($sessionPropertyId)
            ->where('partner_id', $partner->id)
            ->where('country_id', $countryId)
            ->exists()) {
            return (int) $sessionPropertyId;
        }

        return Property::query()
            ->where('partner_id', $partner->id)
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->value('id');
    }
}
