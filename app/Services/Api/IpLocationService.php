<?php

namespace App\Services\Api;

use App\Enums\CityStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\RefCountry;
use App\Models\Setting;
use App\Support\Geo;
use Illuminate\Support\Facades\Cache;
use Stevebauman\Location\Facades\Location;

class IpLocationService
{
    /**
     * Coordinates farther than this from the nearest active city aren't treated as a
     * usable signal — better to fall through to the next resolution tier (IP, then
     * global) than confidently assign a visitor to a country they're nowhere near.
     */
    private const MAX_COORDINATE_MATCH_KM = 300;

    /**
     * Resolve the request IP to an active, operationally-supported Country record.
     * Returns null if the IP cannot be geolocated or the country is not one the
     * business currently operates in (used for country-scoped features like banners).
     * Result is cached for 24 hours per IP.
     */
    public function getCountryFromIp(): ?Country
    {
        $isoCode = $this->resolveIsoCodeFromIp();

        if (! $isoCode) {
            return null;
        }

        $cacheKey = 'country_from_ip:'.request()->ip();

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $country = Country::query()
            ->where('iso_code', strtolower($isoCode))
            ->where('is_active', true)
            ->first();

        if ($country) {
            Cache::put($cacheKey, $country, 86400);
        }

        return $country;
    }

    /**
     * Resolve browser-reported coordinates (navigator.geolocation, sent by the frontend once
     * the visitor grants permission) to an active, operationally-supported Country record —
     * no external reverse-geocoding API involved. Since this business only operates in a known,
     * limited set of countries/cities, "nearest active city we actually operate in" is a more
     * meaningful signal than a coarse per-country centroid, especially for large countries or
     * visitors near a border. Returns null (falls through to the next resolution tier) when the
     * nearest match is farther than MAX_COORDINATE_MATCH_KM — an unreachably distant "nearest"
     * city means this visitor isn't actually near anywhere we operate, not that we should
     * confidently assign them to whichever country happens to be least far away.
     */
    public function getCountryFromCoordinates(?float $lat, ?float $lng): ?Country
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $nearestCityCountryId = City::query()
            ->where('status', CityStatus::Active)
            ->select('country_id')
            ->addSelect(Geo::haversineExpression($lat, $lng))
            ->orderBy('distance_km')
            ->havingRaw('distance_km <= ?', [self::MAX_COORDINATE_MATCH_KM])
            ->value('country_id');

        if (! $nearestCityCountryId) {
            return null;
        }

        return Country::query()
            ->where('id', $nearestCityCountryId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Resolve the request IP to a currency code using the full world reference list
     * (ref_countries), independent of which countries the business operates in.
     * Returns null if the IP cannot be geolocated or has no known currency.
     * Result is cached for 24 hours per IP.
     */
    public function getCurrencyFromIp(): ?string
    {
        $isoCode = $this->resolveIsoCodeFromIp();

        if (! $isoCode) {
            return null;
        }

        $cacheKey = 'currency_from_ip:'.request()->ip();

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $currency = RefCountry::query()->where('iso2', $isoCode)->value('currency');
        $currency = $currency ? strtoupper($currency) : null;

        if ($currency) {
            Cache::put($cacheKey, $currency, 86400);
        }

        return $currency;
    }

    /**
     * Resolve the request IP to an uppercase ISO2 country code via geolocation.
     * Cached for 24 hours per IP since the underlying lookup is a network call;
     * shared by getCountryFromIp() and getCurrencyFromIp() to avoid duplicate lookups.
     */
    private function resolveIsoCodeFromIp(): ?string
    {
        $ip = request()->ip();

        if (! $ip) {
            return null;
        }

        $cacheKey = "ip_iso_code:{$ip}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            if ($token = Setting::get('ipinfo_api_key')) {
                config(['location.ipinfo.token' => $token]);
            }

            $location = Location::get($ip);

            if (! $location || ! $location->countryCode) {
                return null;
            }

            $isoCode = strtoupper($location->countryCode);

            Cache::put($cacheKey, $isoCode, 86400);

            return $isoCode;
        } catch (\Exception) {
            return null;
        }
    }
}
