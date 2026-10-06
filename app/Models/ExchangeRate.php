<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
        ];
    }

    /**
     * Get the conversion rate between two currencies.
     * Returns null if rate not found.
     */
    public static function getRate(string $baseCurrency, string $targetCurrency): ?float
    {
        if (strtoupper($baseCurrency) === strtoupper($targetCurrency)) {
            return 1.0;
        }

        $rate = self::query()
            ->where('base_currency', strtoupper($baseCurrency))
            ->where('target_currency', strtoupper($targetCurrency))
            ->value('rate');

        return $rate ? (float) $rate : null;
    }

    /**
     * Convert an amount from one currency to another.
     * Returns null if rate not found.
     */
    public static function convert(float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        $rate = self::getRate($fromCurrency, $toCurrency);

        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }
}
