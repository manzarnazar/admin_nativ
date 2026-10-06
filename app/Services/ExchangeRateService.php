<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Sync exchange rates for all active countries' currencies.
     * Called by the cron job every 24 hours.
     *
     * @return array{synced: array<string>, failed: array<string>}
     */
    public function syncAll(): array
    {
        // Get unique currencies from active countries + currencies table
        $fromCountries = Country::query()
            ->where('is_active', true)
            ->whereNotNull('currency_code')
            ->distinct()
            ->pluck('currency_code')
            ->toArray();

        $fromCurrencies = Currency::where('is_active', true)
            ->pluck('currency_code')
            ->toArray();

        $currencies = array_values(array_unique(array_merge($fromCountries, $fromCurrencies)));

        $synced = [];
        $failed = [];

        foreach ($currencies as $currency) {
            try {
                $count = $this->syncCurrency($currency);
                $synced[] = "{$currency} ({$count} rates)";
            } catch (\Exception $e) {
                Log::error("Exchange rate sync failed for {$currency}: {$e->getMessage()}");
                $failed[] = "{$currency}: {$e->getMessage()}";
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Fetch and store exchange rates for a single base currency.
     * Returns the number of rates fetched from the API.
     */
    public function syncCurrency(string $baseCurrency): int
    {
        $baseCurrency = strtoupper($baseCurrency);
        $rates = $this->fetchRates($baseCurrency);

        foreach ($rates as $targetCurrency => $rate) {
            $record = ExchangeRate::query()->updateOrCreate(
                [
                    'base_currency' => $baseCurrency,
                    'target_currency' => strtoupper($targetCurrency),
                ],
                [
                    'rate' => $rate,
                ],
            );

            // Force updated_at refresh even when rate value hasn't changed
            // (e.g. INR→INR = 1, INR→BTN = 1 peg) so the cron monitor reflects
            // a successful sync on every run.
            if (! $record->wasChanged()) {
                $record->touch();
            }
        }

        return count($rates);
    }

    /**
     * Fetch rates from the exchange rate API.
     *
     * @return array<string, float>
     */
    private function fetchRates(string $baseCurrency): array
    {
        $apiKey = Setting::get('exchange_rate_api_key');

        // Use authenticated API if key exists, otherwise free API
        $url = $apiKey
            ? "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$baseCurrency}"
            : "https://open.er-api.com/v6/latest/{$baseCurrency}";

        $response = Http::timeout(30)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("API request failed for {$baseCurrency}: HTTP {$response->status()}");
        }

        $data = $response->json();

        if (($data['result'] ?? '') !== 'success') {
            throw new \RuntimeException("API returned error for {$baseCurrency}: ".($data['error-type'] ?? 'unknown'));
        }

        // Free API (open.er-api.com) uses 'rates'; authenticated API (v6.exchangerate-api.com) uses 'conversion_rates'
        return $data['rates'] ?? $data['conversion_rates'] ?? [];
    }
}
