<?php

namespace App\Services\Api;

use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\RefCountry;

class CurrencyConverter
{
    /**
     * Get the target currency with priority: Header → IP → USD.
     */
    public static function getTargetCurrency(): string
    {
        // 1. Header first (user explicitly chose)
        $currency = request()->header('Accept-Currency');
        if ($currency) {
            return strtoupper($currency);
        }

        // 2. IP-based fallback
        $ipCurrency = self::getCurrencyFromIp();
        if ($ipCurrency) {
            return $ipCurrency;
        }

        // 3. Last resort
        return 'USD';
    }

    /**
     * Add converted price fields to a data array.
     * Returns the original array with converted fields added (if applicable).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function addConvertedPrice(
        array $data,
        float $amount,
        ?string $baseCurrency,
        string $priceField = 'base_price_per_night',
    ): array {
        if (! $baseCurrency) {
            return $data;
        }

        $targetCurrency = self::getTargetCurrency();

        if ($targetCurrency === strtoupper($baseCurrency)) {
            // Same currency - use rate of 1.0 and return converted fields
            $symbol = self::getCurrencySymbol($targetCurrency);
            $data['converted_'.$priceField] = $amount;
            $data['converted_currency_code'] = $targetCurrency;
            $data['converted_currency_symbol'] = $symbol;
            $data['exchange_rate'] = 1.0;

            return $data;
        }

        $rate = ExchangeRate::getRate($baseCurrency, $targetCurrency);

        if ($rate === null) {
            // No rate available — fall back to base currency so converted_* fields always exist
            $symbol = self::getCurrencySymbol($baseCurrency);
            $data['converted_'.$priceField] = $amount;
            $data['converted_currency_code'] = strtoupper($baseCurrency);
            $data['converted_currency_symbol'] = $symbol;
            $data['exchange_rate'] = 1.0;

            return $data;
        }

        // Get target currency symbol from ref_countries or hardcode common ones
        $symbol = self::getCurrencySymbol($targetCurrency);

        $data['converted_'.$priceField] = round($amount * $rate, 2);
        $data['converted_currency_code'] = $targetCurrency;
        $data['converted_currency_symbol'] = $symbol;
        $data['exchange_rate'] = $rate;

        return $data;
    }

    /**
     * Get currency from user's IP address.
     * Delegates geolocation to IpLocationService, which resolves against the full
     * world reference list (ref_countries) — not gated by which countries the
     * business operates in.
     */
    private static function getCurrencyFromIp(): ?string
    {
        return app(IpLocationService::class)->getCurrencyFromIp();
    }

    /**
     * Get currency symbol for a currency code.
     */
    private static function getCurrencySymbol(string $currencyCode): string
    {
        $symbol = Country::query()
            ->where('currency_code', strtoupper($currencyCode))
            ->value('currency_symbol');

        if ($symbol) {
            return $symbol;
        }

        // Fallback: check ref_countries
        $symbol = RefCountry::query()
            ->where('currency', strtoupper($currencyCode))
            ->value('currency_symbol');

        return $symbol ?? $currencyCode;
    }
}
