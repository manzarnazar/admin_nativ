<?php

namespace App\Actions;

use App\Models\PropertyRoom;
use App\Models\Tax;
use App\Support\SystemMode;
use Carbon\Carbon;

class CalculatePricingAction
{
    /**
     * Calculate base amount, tax breakdown, and total for a room booking.
     *
     * @return array{base_amount: float, tax_amount: float, total_amount: float, tax_details: array<int, array<string, mixed>>}
     */
    public function handle(
        PropertyRoom $propertyRoom,
        int $nights,
        int $rooms,
        int $countryId,
        ?int $propertyTypeId = null,
        float $discountAmount = 0.0,
    ): array {
        $baseAmount = (float) $propertyRoom->base_price_per_night * $nights * $rooms;
        $taxableAmount = max(0.0, $baseAmount - $discountAmount);

        $taxQuery = Tax::query()
            ->where('country_id', $countryId)
            ->where('status', 'active');

        if (SystemMode::isMulti() && $propertyTypeId !== null) {
            $taxQuery->whereHas('propertyTypes', fn ($q) => $q->where('property_types.id', $propertyTypeId));
        }

        $taxes = $taxQuery->get();

        $taxAmount = 0;
        $taxDetails = [];

        foreach ($taxes as $tax) {
            if ($tax->type->value === 'percentage') {
                $amount = $taxableAmount * ((float) $tax->value / 100);
            } else {
                $amount = (float) $tax->value;
            }

            $taxAmount += $amount;
            $taxDetails[] = [
                'name' => $tax->name,
                'type' => $tax->type->value,
                'rate' => (float) $tax->value,
                'amount' => round($amount, 2),
            ];
        }

        $taxAmount = round($taxAmount, 2);

        return [
            'base_amount' => round($baseAmount, 2),
            'tax_amount' => $taxAmount,
            'total_amount' => round($taxableAmount + $taxAmount, 2),
            'tax_details' => $taxDetails,
        ];
    }

    /**
     * Calculate the number of nights between check-in and check-out (minimum 1).
     */
    public function nights(string $checkIn, string $checkOut): int
    {
        return max(1, (int) Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)));
    }
}
