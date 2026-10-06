<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Booking;
use App\Models\City;
use App\Models\Country;
use App\Models\Review;
use App\Models\User;
use App\Support\DemoMode;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class CustomerReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/customer';

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
        return __('admin.report_customer_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_customer_desc');
    }

    /**
     * A customer isn't tied to a property, so — unlike Booking/Cancellation Report — "the
     * selected country" can only mean "has a booking against a property in that country". A
     * customer who has booked in multiple countries appears (with different numbers) in each
     * country's report, never blended together. $cityId narrows this further to whatever the
     * City filter is currently set to (read live in table() via getTableFilterState()), so the
     * aggregate columns shrink to match the filter, not just which customer rows appear.
     */
    private function countryBookingScope(int $countryId, ?int $cityId): \Closure
    {
        return fn (Builder $query) => $query
            ->where('country_id', $countryId)
            ->when($cityId, fn (Builder $q) => $q->where('ref_city_id', $cityId));
    }

    /**
     * Same "fully-paid, still-valid" definition already established in AllCustomersManage.php /
     * CustomerView.php — reused verbatim rather than inventing a second definition of spend.
     */
    private function paidBookingsQuery(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->where('payment_status', PaymentStatus::Paid);
    }

    /**
     * bookings_count/bookings_sum_total_amount/reviews_count are withCount()/withSum() subquery
     * aliases, not real columns — like refund in BookingReport, they can't be handed to a plain
     * SQL sum() (the alias doesn't exist in the FROM clause once sum() replaces the SELECT list),
     * so the "Total" row for each is computed independently against the filtered set of customer
     * IDs, the same technique already proven for resolveRefundTotal().
     */
    private function resolveBookingCountTotal(QueryBuilder $query, int $countryId, ?int $cityId): int
    {
        $userIds = $query->pluck('id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        return Booking::query()
            ->whereIn('user_id', $userIds)
            ->whereHas('property', $this->countryBookingScope($countryId, $cityId))
            ->count();
    }

    private function resolveSpentTotal(QueryBuilder $query, int $countryId, ?int $cityId, string $currency): string
    {
        $userIds = $query->pluck('id');

        if ($userIds->isEmpty()) {
            return $currency.'0.00';
        }

        $total = $this->paidBookingsQuery(
            Booking::query()
                ->whereIn('user_id', $userIds)
                ->whereHas('property', $this->countryBookingScope($countryId, $cityId))
        )->sum('total_amount');

        return $currency.number_format((float) $total, 2);
    }

    private function resolveReviewCountTotal(QueryBuilder $query, int $countryId, ?int $cityId): int
    {
        $userIds = $query->pluck('id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        return Review::query()
            ->whereIn('user_id', $userIds)
            ->whereHas('property', $this->countryBookingScope($countryId, $cityId))
            ->count();
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = Country::query()->where('id', $user->current_country_id)->value('currency_symbol') ?? '$';
        $countryId = $user->current_country_id;

        // Total Booking/Total Spent/Reviews Given are aggregates across the customer's bookings —
        // read the City filter live rather than only at row-inclusion time, so picking a city
        // narrows these numbers down to that city's slice too, not just which customers appear.
        $cityId = $this->getTableFilterState('city')['city_id'] ?? null;
        $countryScope = $this->countryBookingScope($countryId, $cityId);

        $query = User::query()
            ->where('role', UserRole::Customer)
            ->whereHas('bookings.property', $countryScope)
            ->withCount(['bookings' => fn (Builder $q) => $q->whereHas('property', $countryScope)])
            ->withSum([
                'bookings' => fn (Builder $q) => $this->paidBookingsQuery($q->whereHas('property', $countryScope)),
            ], 'total_amount')
            ->withCount(['reviews' => fn (Builder $q) => $q->whereHas('property', $countryScope)]);

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_customer_name'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.customer_id'))
                    ->searchable()
                    ->state(fn (User $record): Htmlable => static::linkedNameWithIcon(
                        CustomerView::getUrl(['record' => $record->id]),
                        '#'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT),
                    )),

                TextColumn::make('name')
                    ->label(__('admin.customer_name'))
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('admin.contact_info'))
                    ->state(fn (User $record): string => DemoMode::maskEmail($record->email) ?? '-')
                    ->description(fn (User $record): string => $record->phone
                        ? ltrim(($record->dial_code ?? '').' '.(DemoMode::maskPhone($record->phone) ?? ''))
                        : '-')
                    ->searchable(['email', 'phone']),

                TextColumn::make('created_at')
                    ->label(__('admin.joined_date'))
                    ->dateTime('d M Y'),

                TextColumn::make('bookings_count')
                    ->label(__('admin.total_booking'))
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): int => $this->resolveBookingCountTotal($query, $countryId, $cityId))
                    ),

                TextColumn::make('bookings_sum_total_amount')
                    ->label(__('admin.total_spent'))
                    ->formatStateUsing(fn (?string $state): string => $currency.number_format((float) ($state ?? 0), 2))
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): string => $this->resolveSpentTotal($query, $countryId, $cityId, $currency))
                    ),

                TextColumn::make('reviews_count')
                    ->label(__('admin.reviews_given'))
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): int => $this->resolveReviewCountTotal($query, $countryId, $cityId))
                    ),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Suspended => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters($filters = [
                static::dateRangeFilter('created_at'),

                Filter::make('city')
                    ->label(__('admin.city'))
                    ->schema([
                        Select::make('city_id')
                            ->label(__('admin.city'))
                            ->options(City::query()->where('country_id', $countryId)->pluck('name', 'ref_city_id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['city_id'] ?? null,
                        fn (Builder $q, $value) => $q->whereHas('bookings.property', fn (Builder $pq) => $pq->where('country_id', $countryId)->where('ref_city_id', $value))
                    ))
                    ->indicateUsing(fn (array $data): array => filled($data['city_id'] ?? null)
                        ? [Indicator::make(__('admin.city').': '.(City::where('ref_city_id', $data['city_id'])->value('name') ?? $data['city_id']))->removeField('city_id')]
                        : []),

                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        UserStatus::Active->value => __('admin.active'),
                        UserStatus::Suspended->value => __('admin.suspended'),
                    ])
                    ->native(false),

                Filter::make('sort_by')
                    ->label(__('admin.sort_by'))
                    ->schema([
                        Select::make('sort_by')
                            ->label(__('admin.sort_by'))
                            ->options([
                                'highest_booking' => __('admin.highest_booking'),
                                'lowest_booking' => __('admin.lowest_booking'),
                                'highest_spent' => __('admin.highest_spent'),
                                'lowest_spent' => __('admin.lowest_spent'),
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // reorder() clears the table's default "created_at desc" sort first —
                        // otherwise it stays as the primary ORDER BY key (timestamps essentially
                        // never tie) and this filter's own ordering never visibly takes effect.
                        return match ($data['sort_by'] ?? null) {
                            'highest_booking' => $query->reorder()->orderByDesc('bookings_count'),
                            'lowest_booking' => $query->reorder()->orderBy('bookings_count'),
                            'highest_spent' => $query->reorder()->orderByDesc('bookings_sum_total_amount'),
                            'lowest_spent' => $query->reorder()->orderBy('bookings_sum_total_amount'),
                            default => $query,
                        };
                    })
                    ->indicateUsing(fn (array $data): array => filled($data['sort_by'] ?? null)
                        ? [Indicator::make(__('admin.sort_by').': '.__('admin.'.$data['sort_by']))->removeField('sort_by')]
                        : []),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('customer-report')
                    ->exports([
                        'id' => ['label' => 'Customer ID', 'formatter' => fn (User $r): string => '#'.str_pad((string) $r->id, 3, '0', STR_PAD_LEFT)],
                        'name' => 'Customer Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                        'created_at' => ['label' => 'Joined Date', 'formatter' => fn (User $r): string => $r->created_at->format('d M Y')],
                        'bookings_count' => 'Total Booking',
                        'bookings_sum_total_amount' => ['label' => 'Total Spent', 'formatter' => fn (User $r) => $currency.number_format((float) ($r->bookings_sum_total_amount ?? 0), 2)],
                        'reviews_count' => 'Reviews Given',
                        'status' => ['label' => 'Status', 'formatter' => fn (User $r): string => $r->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_customers_yet'))
            ->emptyStateIcon('heroicon-o-users')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            // One "Total" row across all filtered results, not a separate per-page row too.
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
