<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Services\CommissionService;
use App\Support\SystemMode;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\HtmlString;

/**
 * Money columns on this report (see the private methods below for the full formula on each):
 *
 * - Gross Amount: resolveGrossAmount() — base_amount for a normal booking, or the retained-room
 *   figure snapshotted at cancellation time for a Cancelled one (never the full base_amount for a
 *   cancellation, since commission/credit were calculated on what was actually retained).
 *
 * - Net Payout: resolveNetPayout() — calculateCheckInPartnerCredit() for a normal booking (nets
 *   against whatever's actually been collected online so far), or the exact wallet_credit value
 *   snapshotted at cancellation time for a Cancelled one. This is per-booking, replay-style math —
 *   NOT the same "Net Payout" definition FinanceSummaryReport uses (that one is a platform-level
 *   Revenue − Commission − Taxes − Refunds rollup; see that file's own comment block).
 */
class PartnerPayoutReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/partner-payout';

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
        return __('admin.report_partner_payout_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_partner_payout_desc');
    }

    /**
     * Gross Amount a booking is credited against. A Cancelled booking is only ever a row here
     * because it retained a non-zero amount for the partner (the base query excludes 100%-refunded
     * cancellations) — that retained figure, not the booking's full base_amount, is what commission
     * and the credit were actually calculated on (see BookingService::cancelBooking()'s
     * calculateCancellationBreakdown() call), snapshotted into refund_inputs at cancellation time.
     */
    private function resolveGrossAmount(Booking $record): float
    {
        return $record->status === BookingStatus::Cancelled
            ? (float) ($record->refund_inputs['retained_room'] ?? 0)
            : (float) $record->base_amount;
    }

    /**
     * The partner's credited share. For a Cancelled booking this is the exact wallet_credit value
     * already snapshotted in refund_inputs at cancellation time (real history, not recomputed).
     * For everything else it's freshly computed via calculateCheckInPartnerCredit() — safe even
     * for a still-Pending row (nets against what's actually been collected online so far, which
     * is 0/partial for a booking that hasn't checked in yet — an honest preview, not an
     * overstated one).
     */
    private function resolveNetPayout(Booking $record): float
    {
        return $record->status === BookingStatus::Cancelled
            ? (float) ($record->refund_inputs['wallet_credit'] ?? 0)
            : app(CommissionService::class)->calculateCheckInPartnerCredit($record);
    }

    /**
     * Gross Amount/Net Payout branch on refund_inputs (a JSON column) for Cancelled rows, so a
     * plain SQL sum() can't compute their total — same problem, same fix, as Refund in
     * BookingReport: resolve the "Total" row independently against the filtered booking IDs.
     */
    private function resolveGrossAmountTotal(QueryBuilder $query, string $currency): string
    {
        $bookingIds = $query->pluck('id');

        if ($bookingIds->isEmpty()) {
            return $currency.'0.00';
        }

        $total = Booking::query()->whereIn('id', $bookingIds)->get()
            ->sum(fn (Booking $b): float => $this->resolveGrossAmount($b));

        return $currency.number_format($total, 2);
    }

    private function resolveNetPayoutTotal(QueryBuilder $query, string $currency): string
    {
        $bookingIds = $query->pluck('id');

        if ($bookingIds->isEmpty()) {
            return $currency.'0.00';
        }

        $total = Booking::query()->whereIn('id', $bookingIds)->get()
            ->sum(fn (Booking $b): float => $this->resolveNetPayout($b));

        return $currency.number_format($total, 2);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = Country::query()->where('id', $user->current_country_id)->value('currency_symbol') ?? '$';
        $countryId = $user->current_country_id;

        $query = Booking::query()
            ->with([
                'customer' => fn ($q) => $q->withTrashed(),
                'property.partner.user',
                'property.propertyType',
                'walletTransaction',
            ])
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId)->whereNotNull('partner_id'))
            // Mirrors CreditWalletForCheckedInBookings' own eligibility exactly: Completed is
            // always eligible; Confirmed/CheckedIn only once the check-in date has arrived (a
            // date-level check here, not the cron's exact per-property check-in-time cutoff — a
            // report doesn't need hour precision the underlying process itself only runs hourly
            // for anyway); Cancelled only when something was actually retained (wallet_credited_at
            // is always set synchronously at cancellation when retained_room > 0, so a null here
            // means nothing was ever due, not "still pending" — those rows are excluded entirely).
            ->where(function (Builder $q) {
                $q->where('status', BookingStatus::Completed)
                    ->orWhere(fn (Builder $qq) => $qq->where('status', BookingStatus::Cancelled)->whereNotNull('wallet_credited_at'))
                    ->orWhere(fn (Builder $qq) => $qq->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
                        ->whereDate('check_in', '<=', now()->toDateString()));
            });

        return $table
            ->query($query)
            ->defaultSort('check_in', 'desc')
            ->searchPlaceholder(__('admin.search_partners'))
            ->columns([
                TextColumn::make('payout_id')
                    ->label(__('admin.payout_id'))
                    ->state(function (Booking $record): Htmlable {
                        if (! $record->walletTransaction) {
                            return new HtmlString('-');
                        }

                        return static::linkedNameWithIcon(
                            AllPropertiesView::getUrl(['record' => $record->property_id]).'?tab=wallet',
                            '#'.str_pad((string) $record->walletTransaction->id, 4, '0', STR_PAD_LEFT),
                        );
                    }),

                static::propertyColumn(),

                static::bookingNumberColumn(),

                TextColumn::make('gross_amount')
                    ->label(__('admin.gross_amount'))
                    ->state(fn (Booking $record): string => $currency.number_format($this->resolveGrossAmount($record), 2))
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): string => $this->resolveGrossAmountTotal($query, $currency))
                    ),

                TextColumn::make('commission_rate')
                    ->label(__('admin.commission_percent'))
                    ->formatStateUsing(fn (?string $state): string => ($state ?? '0').'%')
                    ->toggleable(),

                static::moneyColumn('tax_amount', __('admin.tax'), $currency)
                    ->toggleable(),

                TextColumn::make('net_payout')
                    ->label(__('admin.net_payout'))
                    ->state(fn (Booking $record): string => $currency.number_format($this->resolveNetPayout($record), 2))
                    ->description(fn (Booking $record): ?string => $record->wallet_credited_at ? __('admin.credited_to_wallet') : null)
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): string => $this->resolveNetPayoutTotal($query, $currency))
                    ),

                TextColumn::make('wallet_credited_at')
                    ->label(__('admin.settlement_date'))
                    ->formatStateUsing(fn (?Carbon $state): string => $state?->format('M d, Y') ?? '-'),

                TextColumn::make('payout_status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(fn (Booking $record): string => $record->wallet_credited_at ? __('admin.processed') : __('admin.pending_settlement'))
                    ->color(fn (Booking $record): string => $record->wallet_credited_at ? 'success' : 'warning'),
            ])
            ->filters($filters = [
                static::dateRangeFilter('check_in'),

                static::propertyTypeFilter($countryId),

                Filter::make('payout_status')
                    ->label(__('admin.status'))
                    ->schema([
                        Select::make('payout_status')
                            ->label(__('admin.status'))
                            ->options([
                                'processed' => __('admin.processed'),
                                'pending_settlement' => __('admin.pending_settlement'),
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['payout_status'] ?? null) {
                            'processed' => $query->whereNotNull('wallet_credited_at'),
                            'pending_settlement' => $query->whereNull('wallet_credited_at'),
                            default => $query,
                        };
                    })
                    ->indicateUsing(fn (array $data): array => filled($data['payout_status'] ?? null)
                        ? [Indicator::make(__('admin.status').': '.__('admin.'.$data['payout_status']))->removeField('payout_status')]
                        : []),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('partner-payout-report')
                    ->exports([
                        'payout_id' => ['label' => 'Payout ID', 'formatter' => fn (Booking $r): string => $r->walletTransaction ? '#'.str_pad((string) $r->walletTransaction->id, 4, '0', STR_PAD_LEFT) : '-'],
                        'property.name' => 'Property Name',
                        'booking_number' => 'Booking Ref',
                        'gross_amount' => ['label' => 'Gross Amount', 'formatter' => fn (Booking $r) => $currency.number_format($this->resolveGrossAmount($r), 2)],
                        'commission_rate' => ['label' => 'Commission %', 'formatter' => fn (Booking $r): string => ($r->commission_rate ?? '0').'%'],
                        'tax_amount' => ['label' => 'Tax', 'formatter' => fn (Booking $r): string => $currency.number_format((float) $r->tax_amount, 2)],
                        'net_payout' => ['label' => 'Net Payout', 'formatter' => fn (Booking $r) => $currency.number_format($this->resolveNetPayout($r), 2)],
                        'wallet_credited_at' => ['label' => 'Settlement Date', 'formatter' => fn (Booking $r): string => $r->wallet_credited_at?->format('M d, Y') ?? '-'],
                        'payout_status' => ['label' => 'Status', 'formatter' => fn (Booking $r): string => $r->wallet_credited_at ? __('admin.processed') : __('admin.pending_settlement')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_payouts_yet'))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            // One "Total" row across all filtered results, not a separate per-page row too.
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
