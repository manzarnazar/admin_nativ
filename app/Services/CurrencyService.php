<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\RefCountry;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    public function __construct(
        private ExchangeRateService $exchangeRateService,
    ) {}

    /**
     * Add a currency from a ref_country record.
     * Deduplicates by currency_code — throws if already exists.
     */
    public function addFromRefCountry(int $refCountryId): Currency
    {
        $ref = RefCountry::findOrFail($refCountryId);

        $code = strtoupper($ref->currency);

        if (Currency::where('currency_code', $code)->exists()) {
            throw new \RuntimeException("Currency {$code} is already added.");
        }

        $isFirst = Currency::count() === 0;

        $currency = Currency::create([
            'currency_name'   => $ref->currency_name,
            'currency_code'   => $code,
            'currency_symbol' => $ref->currency_symbol,
            'country_name'    => $ref->name,
            'country_iso2'    => strtolower($ref->iso2),
            'is_default'      => $isFirst,
            'is_active'       => true,
            'sort_order'      => Currency::max('sort_order') + 1,
        ]);

        // Immediately sync exchange rates for this currency
        try {
            $this->exchangeRateService->syncCurrency($code);
        } catch (\Exception $e) {
            Log::warning("Exchange rate sync failed for {$code} after adding currency: {$e->getMessage()}");
        }

        return $currency;
    }

    /**
     * Seed the default currency from a country record (used by SetupWizard).
     */
    public function seedDefault(string $currencyCode, string $currencyName, string $currencySymbol, string $countryName, string $countryIso2): Currency
    {
        if (Currency::where('currency_code', strtoupper($currencyCode))->exists()) {
            return Currency::where('currency_code', strtoupper($currencyCode))->first();
        }

        $currency = Currency::create([
            'currency_name'   => $currencyName,
            'currency_code'   => strtoupper($currencyCode),
            'currency_symbol' => $currencySymbol,
            'country_name'    => $countryName,
            'country_iso2'    => strtolower($countryIso2),
            'is_default'      => true,
            'is_active'       => true,
            'sort_order'      => 1,
        ]);

        try {
            $this->exchangeRateService->syncCurrency($currency->currency_code);
        } catch (\Exception $e) {
            Log::warning("Exchange rate sync failed for {$currency->currency_code}: {$e->getMessage()}");
        }

        return $currency;
    }

    public function toggle(Currency $currency): void
    {
        $currency->update(['is_active' => ! $currency->is_active]);
    }

    public function delete(Currency $currency): void
    {
        $currency->delete();
    }

    /**
     * Returns all active currencies for API consumption.
     */
    public function getActive(): array
    {
        return Currency::active()
            ->orderBy('sort_order')
            ->get(['currency_name', 'currency_code', 'currency_symbol', 'country_name', 'country_iso2'])
            ->toArray();
    }
}
