<?php

namespace App\Filament\Pages;

use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Country;
use App\Models\Payment;
use App\Models\PropertyWalletTransaction;
use App\Models\Refund;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\MonthlyFinanceRollupService;
use App\Support\SystemMode;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Row = one financial transaction, unified across three otherwise-unrelated tables: Payment
 * (customer payments), Refund (gateway refunds), PropertyWalletTransaction (partner wallet
 * credits/debits). No single SQL query can span these, so — like TaxReport/FinanceSummaryReport
 * — this uses ->records() over three independently-queried, tagged, merged Collections rather
 * than ->query().
 *
 * Source is a simple 3-way bucket for this unified log (Payment→Customer, Refund→Admin,
 * Wallet→Partner) — deliberately coarser than RefundReport's own Platform/Superadmin split,
 * since that level of nuance belongs to the refund-specific report, not this cross-type log.
 *
 * Gateway/Reference ID only apply to Payment (gateway_type/gateway_payment_id) and Refund (via
 * its Payment's gateway_type, and the gateway's own refund_id) — PropertyWalletTransaction is a
 * pure internal ledger with no gateway concept at all, so Wallet rows show "-" for both rather
 * than a fabricated value.
 *
 * Status is normalized to Success/Pending/Failed across all three types for visual consistency
 * in one column, even though the underlying enums differ — PaymentTransactionStatus has 8 cases,
 * RefundStatus has 4, and PropertyWalletTransaction has no status of its own (reuses
 * PartnerWalletReport's own Credit/Debit/Withdrawal-status derivation so the two reports can
 * never disagree about a wallet transaction's state).
 *
 * Search (by customer name) only ever matches Payment/Refund rows — a wallet transaction has no
 * customer at all, so it's excluded entirely once a search term is active rather than shown
 * regardless (it can never be a true match).
 */
class TransactionReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/transaction';

    protected static string $permissionSlug = 'reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-table-page';

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['country' => true, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.report_transaction_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_transaction_desc');
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    /**
     * @return array{label: string, color: string}
     */
    private function statusBadge(string $state): array
    {
        return [
            'label' => match ($state) {
                'success' => __('admin.success'),
                'failed' => __('admin.failed'),
                default => __('admin.pending'),
            },
            'color' => match ($state) {
                'success' => 'success',
                'failed' => 'danger',
                default => 'warning',
            },
        ];
    }

    private function normalizePaymentStatus(PaymentTransactionStatus $status): array
    {
        return $this->statusBadge(match ($status) {
            PaymentTransactionStatus::Success => 'success',
            PaymentTransactionStatus::Failed, PaymentTransactionStatus::Cancelled, PaymentTransactionStatus::Expired, PaymentTransactionStatus::Flagged => 'failed',
            default => 'pending',
        });
    }

    private function normalizeRefundStatus(RefundStatus $status): array
    {
        return $this->statusBadge(match ($status) {
            RefundStatus::Completed => 'success',
            RefundStatus::Failed => 'failed',
            default => 'pending',
        });
    }

    /**
     * Mirrors PartnerWalletReport::resolveStatusKey() exactly — every Credit (and every Debit
     * that isn't a Withdrawal) is settled the instant it's recorded; only a Withdrawal-type
     * Debit carries a real pending/rejected lifecycle.
     */
    private function normalizeWalletStatus(PropertyWalletTransaction $transaction): array
    {
        if ($transaction->type === WalletTransactionType::Credit) {
            return $this->statusBadge('success');
        }

        if ($transaction->reference_type === WalletTransactionReferenceType::Withdrawal && $transaction->reference_id) {
            $status = WithdrawalRequest::query()->where('id', $transaction->reference_id)->toBase()->value('status');

            return match ($status) {
                WithdrawalStatus::Pending->value => $this->statusBadge('pending'),
                WithdrawalStatus::Rejected->value => $this->statusBadge('failed'),
                default => $this->statusBadge('success'),
            };
        }

        return $this->statusBadge('success');
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = $this->getCurrencySymbol();
        $countryId = $user->current_country_id;
        $rollup = app(MonthlyFinanceRollupService::class);

        return $table
            ->records(function (array $filters) use ($countryId, $currency, $rollup): Collection {
                $range = $rollup->resolveDateRange($filters);
                $search = $this->getTableSearch();

                $matchesCustomer = fn (Builder $bookingQuery) => $bookingQuery
                    ->where('guest_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));

                $payments = Payment::query()
                    ->with(['booking'])
                    ->whereHas('booking.property', fn (Builder $q) => $q->where('country_id', $countryId))
                    ->when($range, fn (Builder $q) => $q->whereBetween('created_at', [$range[0], $range[1]]))
                    ->when(filled($search), fn (Builder $q) => $q->whereHas('booking', $matchesCustomer))
                    ->get()
                    ->map(function (Payment $payment) use ($currency): array {
                        $badge = $this->normalizePaymentStatus($payment->status);

                        return [
                            'transaction_id' => 'PAY-'.str_pad((string) $payment->id, 3, '0', STR_PAD_LEFT),
                            'source' => __('admin.customer'),
                            'type' => __('admin.payment'),
                            'amount' => (float) $payment->amount,
                            'amount_formatted' => $currency.number_format((float) $payment->amount, 2),
                            'gateway' => $payment->gateway_type->label(),
                            'reference_id' => $payment->gateway_payment_id ?: '-',
                            'status_label' => $badge['label'],
                            'status_color' => $badge['color'],
                            'date' => $payment->paid_at ?? $payment->created_at,
                        ];
                    });

                $refunds = Refund::query()
                    ->with(['payment.booking'])
                    ->whereHas('payment.booking.property', fn (Builder $q) => $q->where('country_id', $countryId))
                    ->when($range, fn (Builder $q) => $q->whereBetween('created_at', [$range[0], $range[1]]))
                    ->when(filled($search), fn (Builder $q) => $q->whereHas('payment.booking', $matchesCustomer))
                    ->get()
                    ->map(function (Refund $refund) use ($currency): array {
                        $badge = $this->normalizeRefundStatus($refund->status);

                        return [
                            'transaction_id' => 'RF-'.str_pad((string) $refund->id, 3, '0', STR_PAD_LEFT),
                            'source' => __('admin.admin'),
                            'type' => __('admin.refund'),
                            'amount' => (float) $refund->amount,
                            'amount_formatted' => $currency.number_format((float) $refund->amount, 2),
                            'gateway' => $refund->payment?->gateway_type?->label() ?? '-',
                            'reference_id' => $refund->refund_id ?: '-',
                            'status_label' => $badge['label'],
                            'status_color' => $badge['color'],
                            'date' => $refund->processed_at ?? $refund->created_at,
                        ];
                    });

                $walletTransactions = filled($search)
                    ? collect()
                    : PropertyWalletTransaction::query()
                        ->whereHas('wallet.property', fn (Builder $q) => $q->where('country_id', $countryId))
                        ->when($range, fn (Builder $q) => $q->whereBetween('created_at', [$range[0], $range[1]]))
                        ->get()
                        ->map(function (PropertyWalletTransaction $transaction) use ($currency): array {
                            $badge = $this->normalizeWalletStatus($transaction);

                            return [
                                'transaction_id' => 'PTW-'.str_pad((string) $transaction->id, 3, '0', STR_PAD_LEFT),
                                'source' => __('admin.partner'),
                                'type' => __('admin.wallet'),
                                'amount' => (float) $transaction->amount,
                                'amount_formatted' => $currency.number_format((float) $transaction->amount, 2),
                                'gateway' => '-',
                                'reference_id' => '-',
                                'status_label' => $badge['label'],
                                'status_color' => $badge['color'],
                                'date' => $transaction->created_at,
                            ];
                        });

                $records = $payments->concat($refunds)->concat($walletTransactions)
                    ->sortByDesc('date')
                    ->values();

                if ($records->isNotEmpty()) {
                    $records->push([
                        'row_type' => 'total',
                        'transaction_id' => null,
                        'source' => null,
                        'type' => null,
                        'amount_formatted' => $currency.number_format($records->sum('amount'), 2),
                        'gateway' => null,
                        'reference_id' => null,
                        'status_label' => null,
                        'status_color' => null,
                        'date' => null,
                    ]);
                }

                return $records;
            })
            ->paginated(false)
            ->recordClasses(fn (array $record): ?string => ($record['row_type'] ?? null) === 'total' ? 'fi-finance-summary-total-row' : null)
            ->searchPlaceholder(__('admin.search_by_customer_name'))
            ->columns([
                TextColumn::make('transaction_id')
                    ->label(__('admin.transaction_id'))
                    ->formatStateUsing(fn (?string $state): string => $state ?? __('admin.total')),

                TextColumn::make('source')
                    ->label(__('admin.source')),

                TextColumn::make('type')
                    ->label(__('admin.type')),

                TextColumn::make('amount_formatted')
                    ->label(__('admin.amount'))
                    ->weight(fn (array $record): ?string => ($record['row_type'] ?? null) === 'total' ? 'bold' : null),

                TextColumn::make('gateway')
                    ->label(__('admin.gateway'))
                    ->toggleable(),

                TextColumn::make('reference_id')
                    ->label(__('admin.reference_id'))
                    ->toggleable(),

                TextColumn::make('status_label')
                    ->label(__('admin.status'))
                    ->badge()
                    ->color(fn (array $record): string => $record['status_color'] ?? 'gray'),

                TextColumn::make('date')
                    ->label(__('admin.date'))
                    ->formatStateUsing(fn (?Carbon $state): string => $state?->format('d M, Y') ?? '-'),
            ])
            ->filters([
                static::dateRangeFilter('created_at'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('transaction-report')
                    ->exports([
                        'transaction_id' => 'Transaction ID',
                        'source' => 'Source',
                        'type' => 'Type',
                        'amount_formatted' => 'Amount',
                        'gateway' => 'Gateway',
                        'reference_id' => 'Reference ID',
                        'status_label' => 'Status',
                        'date' => ['label' => 'Date', 'formatter' => fn (array $r): string => $r['date']?->format('d M, Y') ?? '-'],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_transactions_yet'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
