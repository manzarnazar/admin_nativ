<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Row = one booking whose commission has actually been earned — scoped to status IN [Confirmed,
 * CheckedIn, Completed] AND payment_status = Paid, the same "paid & valid" definition
 * CustomerReport/MonthlyFinanceRollupService both already use for "commission earned" elsewhere.
 * A Pending/Cancelled/unpaid booking has a commission_amount snapshotted too (commission is
 * calculated at booking time regardless of payment outcome), but showing it here would overstate
 * what the platform has actually earned — this report intentionally excludes those, unlike
 * BookingReport which deliberately shows every status.
 *
 * Booking Amount is base_amount (tax-exclusive) — commission is always calculated on base_amount,
 * never total_amount, matching the rule used throughout CommissionService.
 */
class CommissionReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/commission';

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
        return __('admin.report_commission_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_commission_desc');
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = Country::query()->where('id', $user->current_country_id)->value('currency_symbol') ?? '$';
        $countryId = $user->current_country_id;

        $query = Booking::query()
            ->with(['property.partner.user', 'property.propertyType'])
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId))
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where('payment_status', PaymentStatus::Paid);

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_booking_id_or_customer'))
            ->columns([
                static::partnerColumn(),

                static::propertyColumn(),

                static::bookingNumberColumn()->label(__('admin.booking_id')),

                static::moneyColumn('base_amount', __('admin.booking_amount'), $currency),

                TextColumn::make('commission_rate')
                    ->label(__('admin.commission_percent'))
                    ->formatStateUsing(fn (?string $state): string => ($state ?? '0').'%')
                    ->toggleable(),

                static::moneyColumn('commission_amount', __('admin.commission_amount'), $currency),

                TextColumn::make('created_at')
                    ->label(__('admin.date'))
                    ->date('d M, Y')
                    ->toggleable(),
            ])
            ->filters($filters = [
                static::dateRangeFilter('created_at'),

                static::propertyTypeFilter($countryId),

                static::cityFilter($countryId),

                static::partnerFilter($countryId),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('commission-report')
                    ->exports([
                        'property.partner.user.name' => 'Partner',
                        'property.name' => 'Property',
                        'booking_number' => 'Booking ID',
                        'base_amount' => ['label' => 'Booking Amount', 'formatter' => fn (Booking $r): string => $currency.number_format((float) $r->base_amount, 2)],
                        'commission_rate' => ['label' => 'Commission %', 'formatter' => fn (Booking $r): string => ($r->commission_rate ?? '0').'%'],
                        'commission_amount' => ['label' => 'Commission Amount', 'formatter' => fn (Booking $r): string => $currency.number_format((float) $r->commission_amount, 2)],
                        'created_at' => ['label' => 'Date', 'formatter' => fn (Booking $r): string => $r->created_at->format('d M, Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_bookings_yet'))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
