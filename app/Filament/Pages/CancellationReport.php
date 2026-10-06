<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\ManualRefundStatus;
use App\Enums\RefundStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Booking;
use App\Models\Country;
use App\Models\ManualRefundRequest;
use App\Models\User;
use App\Support\SystemMode;
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
use Illuminate\Support\Carbon;

/**
 * Money columns on this report:
 *
 * - Booking Amount: bookings.total_amount, shown as-is (the original customer-paid amount before
 *   any refund was applied).
 *
 * - Refund Amount / Refund Status: HasReportPageConventions::resolveWinningRefund() per booking —
 *   a manual refund (ManualRefundRequest) if one exists, else the most recent gateway Refund. A
 *   manual refund always supersedes a gateway one for the same booking; resolveRefundTotal() sums
 *   the "Total" row the same way, without double-counting a booking that has both records. A
 *   Cancelled booking with no refund row at all is always "no refund due", never "not started" —
 *   see resolveRefundBadge()'s docblock for why that's guaranteed by BookingService's cancellation
 *   flow rather than assumed here.
 */
class CancellationReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/cancellation';

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
        return __('admin.report_cancellation_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_cancellation_desc');
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
     * Maps the winning refund (resolveWinningRefund()'s precedence) to the 4 states shown as a
     * badge. A cancellation with no refund record at all is always non-refundable, never "not
     * started yet": BookingService only ever creates a Refund row when the calculated refund
     * amount is greater than 0, and it does so atomically in the same transaction that marks the
     * booking Cancelled — so by the time a booking is Cancelled, a real refund due already has
     * its Refund row.
     *
     * @return array{state: string, label: string, color: string}
     */
    private function resolveRefundBadge(Booking $record): array
    {
        $refund = static::resolveWinningRefund($record);

        if (! $refund) {
            return ['state' => 'no_refund_due', 'label' => __('admin.no_refund_due'), 'color' => 'gray'];
        }

        if ($refund instanceof ManualRefundRequest) {
            return $refund->status === ManualRefundStatus::Transferred
                ? ['state' => 'processed', 'label' => __('admin.processed'), 'color' => 'success']
                : ['state' => 'pending', 'label' => __('admin.pending'), 'color' => 'warning'];
        }

        return match ($refund->status) {
            RefundStatus::Completed => ['state' => 'processed', 'label' => __('admin.processed'), 'color' => 'success'],
            RefundStatus::Failed => ['state' => 'failed', 'label' => __('admin.failed'), 'color' => 'danger'],
            default => ['state' => 'pending', 'label' => __('admin.pending'), 'color' => 'warning'],
        };
    }

    /**
     * @return array<string, string>
     */
    private function refundStatusOptions(): array
    {
        return [
            'no_refund_due' => __('admin.no_refund_due'),
            'pending' => __('admin.pending'),
            'processed' => __('admin.processed'),
            'failed' => __('admin.failed'),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = $this->getCurrencySymbol();
        $countryId = $user->current_country_id;

        $query = Booking::query()
            ->where('status', BookingStatus::Cancelled)
            ->with([
                'customer' => fn ($q) => $q->withTrashed(),
                'property.partner.user',
                'property.propertyType',
                'payments.refunds',
                'manualRefundRequest',
            ])
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId));

        return $table
            ->query($query)
            ->defaultSort('cancelled_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_booking_id_or_customer'))
            ->columns([
                static::bookingNumberColumn(),

                static::customerNameColumn(),

                static::propertyColumn(),

                static::partnerColumn(),

                TextColumn::make('cancelled_at')
                    ->label(__('admin.cancellation_date'))
                    ->formatStateUsing(fn (?Carbon $state): string => $state?->format('M d, Y') ?? '-'),

                static::moneyColumn('total_amount', __('admin.booking_amount'), $currency)
                    ->toggleable(),

                TextColumn::make('refund_amount')
                    ->label(__('admin.refund_amount'))
                    ->getStateUsing(function (Booking $record) use ($currency): string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund ? $currency.number_format((float) $refund->amount, 2) : '-';
                    })
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): string => static::resolveRefundTotal($query, $currency))
                    ),

                TextColumn::make('refund_status')
                    ->label(__('admin.refund_status'))
                    ->badge()
                    ->state(fn (Booking $record): string => $this->resolveRefundBadge($record)['label'])
                    ->color(fn (Booking $record): string => $this->resolveRefundBadge($record)['color']),
            ])
            ->filters($filters = [
                static::dateRangeFilter('cancelled_at'),

                static::propertyTypeFilter($countryId),

                static::cityFilter($countryId),

                static::partnerFilter($countryId),

                Filter::make('refund_status')
                    ->label(__('admin.refund_status'))
                    ->schema([
                        Select::make('refund_status')
                            ->label(__('admin.refund_status'))
                            ->options($this->refundStatusOptions())
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $state = $data['refund_status'] ?? null;

                        if (! $state) {
                            return $query;
                        }

                        // Refund status is derived across two tables (ManualRefundRequest,
                        // gateway Refund), not a real column — narrow the same way
                        // resolveRefundTotal() aggregates: resolve the matching booking IDs
                        // in PHP against the already-scoped query, then constrain by ID.
                        $matchingIds = (clone $query)->get()
                            ->filter(fn (Booking $booking): bool => $this->resolveRefundBadge($booking)['state'] === $state)
                            ->pluck('id');

                        return $query->whereIn('id', $matchingIds);
                    })
                    ->indicateUsing(function (array $data): array {
                        $state = $data['refund_status'] ?? null;

                        if (! $state) {
                            return [];
                        }

                        return [
                            Indicator::make(__('admin.refund_status').': '.($this->refundStatusOptions()[$state] ?? $state))
                                ->removeField('refund_status'),
                        ];
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('cancellation-report')
                    ->exports([
                        'booking_number' => 'Booking ID',
                        'customer.name' => 'Customer Name',
                        'property.name' => 'Property',
                        'property.propertyType.name' => 'Property Type',
                        'property.partner.user.name' => 'Partner',
                        'cancelled_at' => ['label' => 'Cancelled Date', 'formatter' => fn (Booking $r): string => $r->cancelled_at?->format('M d, Y') ?? '-'],
                        'total_amount' => ['label' => 'Booking Amount', 'formatter' => fn (Booking $r): string => $currency.number_format((float) $r->total_amount, 2)],
                        'refund_amount' => ['label' => 'Refund Amount', 'formatter' => function (Booking $r) use ($currency): string {
                            $refund = static::resolveWinningRefund($r);

                            return $refund ? $currency.number_format((float) $refund->amount, 2) : '-';
                        }],
                        'refund_status' => ['label' => 'Refund Status', 'formatter' => fn (Booking $r): string => $this->resolveRefundBadge($r)['label']],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_cancellations_yet'))
            ->emptyStateIcon('heroicon-o-x-circle')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            // One "Total" row across all filtered results, not a separate per-page row too.
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
