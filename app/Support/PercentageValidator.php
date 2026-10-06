<?php

namespace App\Support;

/**
 * Single shared guard for every percentage-shaped input in the app (commission
 * rates, refund percentages, advance/deposit percentages, percentage-type tax
 * and discount values, referral reward percentages) so the 0-100 bound is
 * enforced once, at the service layer, instead of trusted from the form alone.
 */
class PercentageValidator
{
    public static function assertValid(float $value, string $label = 'Percentage'): void
    {
        if ($value < 0 || $value > 100) {
            throw new \RuntimeException("{$label} must be between 0 and 100, got {$value}.");
        }
    }

    /**
     * For fields with a percentage-vs-fixed-amount toggle (tax value, promo/coupon
     * discount value): the 0-100 bound only applies when the type is actually
     * "percentage" — a fixed-amount value is a currency amount with no such cap.
     * $type/$percentageValue are compared loosely so both an enum instance and its
     * ->value string work as $type, and $percentageValue is compared as a string.
     */
    public static function assertValidForType(mixed $type, string $percentageValue, mixed $value, string $label = 'Percentage'): void
    {
        $type = $type instanceof \BackedEnum ? $type->value : $type;

        if ($type === $percentageValue && $value !== null) {
            self::assertValid((float) $value, $label);
        }
    }

    /**
     * Clamp instead of reject — for values sourced from system Settings and
     * consumed by an automatic background flow (e.g. issuing a referral coupon
     * on signup/check-in) rather than a validated admin form submission. Throwing
     * there would break the triggering user flow itself; clamping keeps the
     * percentage safe while letting that flow complete.
     */
    public static function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
