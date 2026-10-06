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
use App\Support\SystemMode;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Money columns on this report:
 *
 * - Payment: bookings.total_amount — the full customer-paid amount, tax included, for every
 *   booking in the current country regardless of status (unlike CustomerReport's paidBookingsQuery()
 *   scope, this report intentionally shows all bookings, not just paid/valid ones).
 *
 * - Refund: HasReportPageConventions::resolveWinningRefund() per booking — a manual refund
 *   (ManualRefundRequest) if one exists, else the most recent gateway Refund. A manual refund
 *   always supersedes a gateway one for the same booking; see resolveRefundTotal() for how the
 *   "Total" row sums this without double-counting.
 *
 * - Commission: bookings.commission_amount, shown as-is (no derived math).
 */
class BookingReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/booking';

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
        return __('admin.report_booking_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_booking_desc');
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
     * @return array{amount: string, status: ?string}
     */
    private function resolveRefundDisplay(Booking $record, string $currency): array
    {
        $refund = static::resolveWinningRefund($record);

        return [
            'amount' => $refund ? $currency.number_format((float) $refund->amount, 2) : '-',
            'status' => $refund?->status->label(),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = $this->getCurrencySymbol();
        $countryId = $user->current_country_id;

        $query = Booking::query()
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
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_booking_id_or_customer'))
            ->columns([
                static::bookingNumberColumn(),

                static::customerNameColumn(),

                static::propertyColumn(),

                static::partnerColumn(),

                TextColumn::make('check_in')
                    ->label(__('admin.check_in_out'))
                    ->formatStateUsing(fn (Booking $record): string => $record->check_in->format('M d, Y').' → '.$record->check_out->format('M d, Y')),

                static::moneyColumn('total_amount', __('admin.payment'), $currency)
                    ->description(fn (Booking $record): ?string => $record->payment_method?->label())
                    ->toggleable(),

                TextColumn::make('refund')
                    ->label(__('admin.refund'))
                    ->getStateUsing(fn (Booking $record): string => $this->resolveRefundDisplay($record, $currency)['amount'])
                    ->description(fn (Booking $record): ?string => $this->resolveRefundDisplay($record, $currency)['status'])
                    ->toggleable()
                    ->summarize(
                        // Plain Summarizer, not Sum: Sum::getSelectStatements() unconditionally
                        // injects sum(bookings.refund) into Filament's batched summary query
                        // regardless of ->using() — "refund" isn't a real column, so that batch
                        // query fails. The base Summarizer contributes nothing to that batch and
                        // only ever runs through ->using(), which is all this needs.
                        Summarizer::make()->using(fn (QueryBuilder $query): string => static::resolveRefundTotal($query, $currency))
                    ),

                static::moneyColumn('commission_amount', __('admin.commission'), $currency)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->color(fn (BookingStatus $state): string => $state->color()),
            ])
            ->filters($filters = [
                static::dateRangeFilter('created_at'),

                static::propertyTypeFilter($countryId),

                static::cityFilter($countryId),

                static::partnerFilter($countryId),

                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect(BookingStatus::cases())
                        ->reject(fn (BookingStatus $s): bool => $s === BookingStatus::Pending)
                        ->mapWithKeys(fn (BookingStatus $s) => [$s->value => $s->label()])
                        ->toArray())
                    ->native(false),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('booking-report')
                    ->exports([
                        'booking_number' => 'Booking ID',
                        'customer.name' => 'Customer Name',
                        'property.name' => 'Property',
                        'property.propertyType.name' => 'Property Type',
                        'property.partner.user.name' => 'Partner',
                        'check_in' => ['label' => 'Check-in', 'formatter' => fn (Booking $r): string => $r->check_in->format('M d, Y')],
                        'check_out' => ['label' => 'Check-out', 'formatter' => fn (Booking $r): string => $r->check_out->format('M d, Y')],
                        'total_amount' => ['label' => 'Payment', 'formatter' => fn (Booking $r): string => $currency.number_format((float) $r->total_amount, 2)],
                        'refund' => ['label' => 'Refund', 'formatter' => fn (Booking $r): string => $this->resolveRefundDisplay($r, $currency)['amount']],
                        'commission_amount' => ['label' => 'Commission', 'formatter' => fn (Booking $r): string => $r->commission_amount ? $currency.number_format((float) $r->commission_amount, 2) : '-'],
                        'status' => ['label' => 'Status', 'formatter' => fn (Booking $r): string => $r->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_bookings_yet'))
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            // One "Total" row across all filtered results, not a separate per-page row too.
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
