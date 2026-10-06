<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Enums\SetupTask;
use App\Models\Booking;
use App\Models\CommissionPartnerOverride;
use App\Models\CommissionRate;
use App\Models\CountrySetupTask;
use App\Models\Refund;
use App\Support\PercentageValidator;

class CommissionService
{
    /**
     * Resolve the applicable commission rate (%) for a booking.
     *
     * Hierarchy (highest priority first):
     *  1. Partner override for this country + partner + property type
     *  2. Country rate for this property type
     *  3. Country default rate (property_type_id IS NULL)
     *  4. 0% (no commission configured)
     */
    public function resolveRate(int $countryId, ?int $partnerId, int $propertyTypeId): float
    {
        if ($partnerId) {
            $override = CommissionPartnerOverride::query()
                ->where('country_id', $countryId)
                ->where('partner_id', $partnerId)
                ->where('property_type_id', $propertyTypeId)
                ->value('rate');

            if ($override !== null) {
                return (float) $override;
            }
        }

        $typeRate = CommissionRate::query()
            ->where('country_id', $countryId)
            ->where('property_type_id', $propertyTypeId)
            ->value('rate');

        if ($typeRate !== null) {
            return (float) $typeRate;
        }

        $defaultRate = CommissionRate::query()
            ->where('country_id', $countryId)
            ->whereNull('property_type_id')
            ->value('rate');

        return $defaultRate !== null ? (float) $defaultRate : 0.0;
    }

    /**
     * Calculate the commission amount from a total and a rate (%).
     */
    public function calculateCommission(float $totalAmount, float $rate): float
    {
        return round($totalAmount * $rate / 100, 2);
    }

    /**
     * The partner's share of an amount already known to be theirs: the amount minus
     * commission, floored at 0. Used for the cancellation-retained-fee credit (where
     * $baseAmount is the already-computed retained room amount, not the booking's full
     * base_amount) — NOT for the check-in credit, which needs calculateCheckInPartnerCredit()
     * instead, since a retained cancellation fee is always settled synchronously in the same
     * transaction as the cancellation itself, unlike a booking's original payment.
     *
     * Deliberately ignores any promo discount — a promo is absorbed entirely by
     * the platform, never by the partner. Commission (and this credit) is always
     * calculated on the original, undiscounted room amount.
     */
    public function calculatePartnerCredit(float $baseAmount, float $commissionAmount): float
    {
        return max(0.0, round($baseAmount - $commissionAmount, 2));
    }

    /**
     * The partner's net share of a booking at check-in — how much of what's actually been
     * collected online (via a real payment gateway) belongs to the partner after commission,
     * scaled to exclude tax (commission and partner credit are always tax-exclusive, matching
     * calculatePartnerCredit()). Unlike that method, this accounts for partial-payment,
     * Pay-at-Property, and manually-recorded cash/UPI bookings, where the property collects
     * some or all of the total directly — that portion never reaches the platform, so it was
     * never the platform's to redistribute. Can be negative: a booking with nothing collected
     * via a real gateway means the partner owes the platform its commission share instead of
     * receiving a credit.
     */
    public function calculateCheckInPartnerCredit(Booking $booking): float
    {
        $totalAmount = (float) $booking->total_amount;
        $baseAmount = (float) $booking->base_amount;
        $commissionAmount = (float) $booking->commission_amount;

        if ($totalAmount <= 0.0 || $baseAmount <= 0.0) {
            return round(0.0 - $commissionAmount, 2);
        }

        $onlineCollected = $this->resolveOnlineCollectedAmount($booking);

        // Backward compatibility only: some admin-created bookings (and
        // Booking::factory()'s default state) mark payment_status=Paid without ever
        // creating a Payment row. Assume the full total was collected — reduces to
        // exactly calculatePartnerCredit(base_amount, commission_amount), unchanged
        // from before this method existed. Deliberately NOT shared with
        // CancellationPolicyService — that path has always treated "no payment row"
        // as "nothing collected" (paymentAmount = 0), and changing that is a
        // separate decision this fallback shouldn't make silently.
        if ($onlineCollected <= 0.0 && $booking->payment_status === PaymentStatus::Paid && ! $booking->payments()->exists()) {
            $onlineCollected = $totalAmount;
        }

        $onlineRoomPortion = $onlineCollected * ($baseAmount / $totalAmount);

        return round($onlineRoomPortion - $commissionAmount, 2);
    }

    /**
     * Sum of a booking's successful payments actually collected via a real payment
     * gateway — the platform only ever holds money that passed through Razorpay/
     * Stripe/Flutterwave etc. A Manual-gateway Payment (cash/UPI recorded by an
     * admin or partner, always flagged collected_at_property in gateway_response)
     * is money the property collected directly, and is deliberately excluded here
     * even when it's the booking's only payment and payment_status=Paid —
     * redistributing it via the wallet would double-pay the partner for money the
     * platform never touched.
     *
     * Shared by calculateCheckInPartnerCredit() and
     * CancellationPolicyService::calculateCancellationBreakdown(), so check-in and
     * cancellation can never disagree about what the platform actually collected
     * for the same booking.
     */
    public function resolveOnlineCollectedAmount(Booking $booking): float
    {
        return (float) $booking->payments()
            ->where('status', PaymentTransactionStatus::Success)
            ->where('gateway_type', '!=', PaymentGateway::Manual)
            ->sum('amount');
    }

    /**
     * What fraction of a booking's total_amount the platform actually holds right now,
     * net of any completed refunds on the online (non-Manual) portion — 0 if nothing was
     * collected online, 1 if the full total was. Used to prorate any total-amount-based
     * figure (most notably tax_amount, for TaxReport's real-remittance column) down to
     * what the platform genuinely received, since a Manual/cash payment never reaches
     * the platform and a refunded online payment no longer sits in its account either.
     */
    public function resolveOnlineCollectionRatio(Booking $booking): float
    {
        $totalAmount = (float) $booking->total_amount;

        if ($totalAmount <= 0.0) {
            return 0.0;
        }

        $netOnlineCollected = $this->resolveOnlineCollectedAmount($booking) - $this->resolveOnlineRefundedAmount($booking);

        return min(1.0, max(0.0, $netOnlineCollected / $totalAmount));
    }

    /**
     * Sum of completed refunds issued against a booking's online (non-Manual) payments —
     * the counterpart to resolveOnlineCollectedAmount() needed to know what the platform
     * still actually holds after a cancellation settles.
     */
    private function resolveOnlineRefundedAmount(Booking $booking): float
    {
        return (float) Refund::query()
            ->whereHas('payment', fn ($q) => $q->where('booking_id', $booking->id)->where('gateway_type', '!=', PaymentGateway::Manual))
            ->where('status', RefundStatus::Completed)
            ->sum('amount');
    }

    /**
     * Resolve the applicable commission rate along with its source and DB record ID.
     *
     * Returns the same rate as resolveRate() but also tells callers WHY that rate applied,
     * so the booking can snapshot commission_rate_id and commission_source for audit purposes.
     *
     * @return array{rate: float, rate_id: ?int, source: string}
     */
    public function resolveRateWithDetails(int $countryId, ?int $partnerId, int $propertyTypeId): array
    {
        if ($partnerId) {
            $override = CommissionPartnerOverride::query()
                ->where('country_id', $countryId)
                ->where('partner_id', $partnerId)
                ->where('property_type_id', $propertyTypeId)
                ->first();

            if ($override !== null) {
                return ['rate' => (float) $override->rate, 'rate_id' => null, 'source' => 'partner_override'];
            }
        }

        $typeRate = CommissionRate::query()
            ->where('country_id', $countryId)
            ->where('property_type_id', $propertyTypeId)
            ->first();

        if ($typeRate !== null) {
            return ['rate' => (float) $typeRate->rate, 'rate_id' => $typeRate->id, 'source' => 'country_type_override'];
        }

        $defaultRate = CommissionRate::query()
            ->where('country_id', $countryId)
            ->whereNull('property_type_id')
            ->first();

        if ($defaultRate !== null) {
            return ['rate' => (float) $defaultRate->rate, 'rate_id' => $defaultRate->id, 'source' => 'country_default'];
        }

        return ['rate' => 0.0, 'rate_id' => null, 'source' => 'none'];
    }

    /**
     * Guard against a rate outside the valid 0-100 percentage range. Filament forms
     * already enforce this, but every setter validates independently so no future
     * caller (tinker, a job, a seeder) can silently persist an invalid rate that
     * would flow into a negative or oversized partner wallet credit.
     */
    private function assertValidRate(float $rate): void
    {
        PercentageValidator::assertValid($rate, 'Commission rate');
    }

    /**
     * Upsert the default country commission rate.
     */
    public function setCountryRate(int $countryId, float $rate): CommissionRate
    {
        $this->assertValidRate($rate);

        $commissionRate = CommissionRate::query()->updateOrCreate(
            ['country_id' => $countryId, 'property_type_id' => null],
            ['rate' => $rate],
        );

        CountrySetupTask::markComplete(SetupTask::Commission, $countryId);

        return $commissionRate;
    }

    /**
     * Upsert a property-type-specific rate for a country.
     */
    public function setPropertyTypeRate(int $countryId, int $propertyTypeId, float $rate): CommissionRate
    {
        $this->assertValidRate($rate);

        return CommissionRate::query()->updateOrCreate(
            ['country_id' => $countryId, 'property_type_id' => $propertyTypeId],
            ['rate' => $rate],
        );
    }

    /**
     * Delete a commission rate row.
     */
    public function deleteRate(CommissionRate $rate): void
    {
        $rate->delete();
    }

    /**
     * Upsert a partner override.
     *
     * @param  array{country_id: int, partner_id: int, property_type_id: int, rate: float, description?: ?string}  $data
     */
    public function upsertPartnerOverride(array $data): CommissionPartnerOverride
    {
        $this->assertValidRate((float) $data['rate']);

        return CommissionPartnerOverride::query()->updateOrCreate(
            [
                'country_id' => $data['country_id'],
                'partner_id' => $data['partner_id'],
                'property_type_id' => $data['property_type_id'],
            ],
            [
                'rate' => $data['rate'],
                'description' => $data['description'] ?? null,
            ],
        );
    }

    /**
     * Delete a partner override.
     */
    public function deletePartnerOverride(CommissionPartnerOverride $override): void
    {
        $override->delete();
    }
}
