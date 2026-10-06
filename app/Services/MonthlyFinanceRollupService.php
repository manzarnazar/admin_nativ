<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ManualRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\Booking;
use App\Models\ManualRefundRequest;
use App\Models\PropertyWalletTransaction;
use App\Models\Refund;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Monthly platform-finance rollup, merged from four independent subsystems that each recognize
 * money at a different point in time. Shared by FinanceSummaryReport (full 7-metric breakdown)
 * and RevenueReport (a 5-metric subset) so the two reports can never disagree about a given
 * month's numbers — both call this same service rather than each computing their own query.
 *
 * - Total Revenue: SUM(bookings.total_amount) — the full customer-paid amount, tax included, for
 *   bookings in the canonical "paid & valid" scope (status IN [Confirmed, CheckedIn, Completed] AND
 *   payment_status = Paid — the same definition CustomerReport's paidBookingsQuery() uses). Grouped
 *   by the booking's created_at month (when the revenue was recognized).
 *
 * - Commission Earned: SUM(bookings.commission_amount), same booking scope and month basis as
 *   Total Revenue.
 *
 * - Taxes: SUM(bookings.tax_amount), same booking scope and month basis. Per docbyteam.md, tax is
 *   collected by the platform and never shared with the partner or credited to the property
 *   wallet — it's shown here purely for visibility, not netted against partner earnings anywhere
 *   but this row's own Net Payout figure.
 *
 * - Total Refunds: manual refunds (ManualRefundRequest where status = Transferred, summed by their
 *   own transferred_at month) plus gateway refunds (Refund where status = Completed, summed by
 *   their own processed_at month) — excluding any booking that already has a manual refund, since
 *   a manual refund always supersedes a gateway one for the same booking (same precedence
 *   HasReportPageConventions::resolveWinningRefund() uses per-booking elsewhere).
 *
 * - Net Payout: Total Revenue − Commission Earned − Taxes − Total Refunds, computed per row from
 *   the four columns above. This is a "what's left in the pool for partners" figure — NOT the
 *   platform's own earned revenue (that's Commission Earned alone) — and NOT the same thing as
 *   Partner Wallet Credit below.
 *
 * - Partner Wallet Credit: SUM(PropertyWalletTransaction.amount) where type = Credit, minus the
 *   same where type = Debit, restricted to reference_type IN [BookingRevenue, CancellationRevenue]
 *   (excludes withdrawal debits) — the real ledger of money that actually moved into partner
 *   wallets, grouped by each transaction's own created_at month. This lags Total Revenue because
 *   wallet credit happens at check-in, not at booking time.
 *
 * - Withdrawal: SUM(WithdrawalRequest.amount) where status = Approved (the "actually paid out"
 *   state — same definition PartnerReport's paidOutSubquery() uses), grouped by processed_at (set
 *   once, at approval time — not created_at, which is when the partner merely requested it).
 */
class MonthlyFinanceRollupService
{
    /**
     * dateRangeFilter()'s own ->query() closure never runs for a custom-data (->records())
     * Filament table — Filament only hands the page the raw filter form data — so the same
     * "range" field's flatpickr string ("Jul 1, 2026 to Jul 15, 2026") gets parsed here instead.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function resolveDateRange(array $filters): ?array
    {
        $range = $filters['created_at']['range'] ?? null;

        if (blank($range) || ! str_contains($range, ' to ')) {
            return null;
        }

        [$from, $to] = array_map('trim', explode(' to ', $range, 2));

        try {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return Collection<string, float> summed $column, keyed by 'Y-m', for rows whose
     *                                   $dateColumn falls in $range (when given)
     */
    private function monthlySum(Builder $query, string $column, string $dateColumn, ?array $range): Collection
    {
        if ($range) {
            $query->whereBetween($dateColumn, [$range[0]->toDateTimeString(), $range[1]->toDateTimeString()]);
        }

        return $query
            ->selectRaw("DATE_FORMAT($dateColumn, '%Y-%m') as ym, SUM($column) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->map(fn ($value): float => (float) $value);
    }

    private function paidBookingsScope(Builder $query, int $countryId): Builder
    {
        return $query
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId))
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where('payment_status', PaymentStatus::Paid);
    }

    /**
     * @return array<string, array<string, float>> keyed by 'Y-m', each a metric => amount map
     *                                             (revenue, commission, taxes, refunds,
     *                                             net_payout, wallet_credit, withdrawal)
     */
    public function monthlyRows(int $countryId, ?array $range): array
    {
        $revenue = $this->monthlySum(
            $this->paidBookingsScope(Booking::query(), $countryId),
            'total_amount',
            'created_at',
            $range,
        );

        $commission = $this->monthlySum(
            $this->paidBookingsScope(Booking::query(), $countryId),
            'commission_amount',
            'created_at',
            $range,
        );

        $taxes = $this->monthlySum(
            $this->paidBookingsScope(Booking::query(), $countryId),
            'tax_amount',
            'created_at',
            $range,
        );

        $manualRefunds = $this->monthlySum(
            ManualRefundRequest::query()
                ->where('status', ManualRefundStatus::Transferred)
                ->whereHas('booking.property', fn (Builder $q) => $q->where('country_id', $countryId)),
            'amount',
            'transferred_at',
            $range,
        );

        $gatewayRefunds = $this->monthlySum(
            Refund::query()
                ->where('status', RefundStatus::Completed)
                ->whereHas('payment.booking.property', fn (Builder $q) => $q->where('country_id', $countryId))
                ->whereDoesntHave('payment.booking.manualRefundRequest'),
            'amount',
            'processed_at',
            $range,
        );

        $walletCredits = $this->monthlySum(
            PropertyWalletTransaction::query()
                ->where('type', WalletTransactionType::Credit)
                ->whereIn('reference_type', [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue])
                ->whereHas('wallet.property', fn (Builder $q) => $q->where('country_id', $countryId)),
            'amount',
            'created_at',
            $range,
        );

        $walletDebits = $this->monthlySum(
            PropertyWalletTransaction::query()
                ->where('type', WalletTransactionType::Debit)
                ->whereIn('reference_type', [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue])
                ->whereHas('wallet.property', fn (Builder $q) => $q->where('country_id', $countryId)),
            'amount',
            'created_at',
            $range,
        );

        $withdrawals = $this->monthlySum(
            WithdrawalRequest::query()
                ->where('status', WithdrawalStatus::Approved)
                ->whereHas('wallet.property', fn (Builder $q) => $q->where('country_id', $countryId)),
            'amount',
            'processed_at',
            $range,
        );

        $months = collect([$revenue, $commission, $taxes, $manualRefunds, $gatewayRefunds, $walletCredits, $walletDebits, $withdrawals])
            ->flatMap(fn (Collection $c) => $c->keys())
            ->unique()
            ->sort()
            ->values();

        return $months->mapWithKeys(function (string $ym) use ($revenue, $commission, $taxes, $manualRefunds, $gatewayRefunds, $walletCredits, $walletDebits, $withdrawals): array {
            $rev = $revenue->get($ym, 0.0);
            $comm = $commission->get($ym, 0.0);
            $tax = $taxes->get($ym, 0.0);
            $refunds = $manualRefunds->get($ym, 0.0) + $gatewayRefunds->get($ym, 0.0);
            $walletCredit = $walletCredits->get($ym, 0.0) - $walletDebits->get($ym, 0.0);
            $withdrawal = $withdrawals->get($ym, 0.0);

            return [$ym => [
                'revenue' => $rev,
                'commission' => $comm,
                'taxes' => $tax,
                'refunds' => $refunds,
                'net_payout' => $rev - $comm - $tax - $refunds,
                'wallet_credit' => $walletCredit,
                'withdrawal' => $withdrawal,
            ]];
        })->all();
    }

    /**
     * @param  array<string, array<string, float>>  $rows
     * @return array<string, float>
     */
    public function totalsRow(array $rows): array
    {
        return collect($rows)->reduce(function (array $carry, array $row): array {
            foreach ($row as $key => $value) {
                $carry[$key] = ($carry[$key] ?? 0.0) + $value;
            }

            return $carry;
        }, []);
    }
}
