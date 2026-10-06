<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\ReviewStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Property;
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
 * Row = one property. "Total Bookings" excludes Expired/PendingPayment/Cancelled, matching
 * AllBookingsManage::getBookingStats()'s "total" definition — the closest existing precedent to
 * a headline "how many real bookings has this property had" count (Dashboard's own stat keeps
 * Cancelled in the funnel; BookingReport itself deliberately shows every status). "Rooms" is
 * physical room inventory (SUM(property_rooms.total_rooms)), matching PropertyManage::
 * singleModeTable()'s convention — not a count of room *types*.
 */
class PropertyReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/property';

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
        return __('admin.report_property_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_property_desc');
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $countryId = $user->current_country_id;

        $query = Property::query()
            ->where('country_id', $countryId)
            ->whereIn('status', [PropertyStatus::Active, PropertyStatus::Inactive, PropertyStatus::Suspended])
            ->with(['propertyType', 'refCity', 'refState', 'partner.user'])
            ->withSum('rooms', 'total_rooms')
            ->withCount(['bookings' => fn (Builder $q) => $q->whereNotIn('status', [
                BookingStatus::Expired, BookingStatus::PendingPayment, BookingStatus::Cancelled,
            ])])
            ->withAvg(['reviews as reviews_avg_rating' => fn (Builder $q) => $q->where('status', ReviewStatus::Published)], 'rating')
            ->withCount(['reviews as reviews_count' => fn (Builder $q) => $q->where('status', ReviewStatus::Published)]);

        return $table
            ->query($query)
            ->searchPlaceholder(__('admin.search_partners'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.property_id'))
                    ->html()
                    ->state(fn (Property $record): Htmlable => static::linkedNameWithIcon(
                        AllPropertiesView::getUrl(['record' => $record->id]),
                        '#'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT),
                    )),

                TextColumn::make('name')
                    ->label(__('admin.property'))
                    ->searchable()
                    ->description(fn (Property $record): ?string => $record->propertyType?->name)
                    ->weight('medium'),

                TextColumn::make('refCity.name')
                    ->label(__('admin.city'))
                    ->state(fn (Property $record): string => $record->refCity?->name ?? '-'),

                TextColumn::make('partner.user.name')
                    ->label(__('admin.partner'))
                    ->state(fn (Property $record): string => $record->partner?->user?->name ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('partner.user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    }),

                TextColumn::make('rooms_sum_total_rooms')
                    ->label(__('admin.rooms'))
                    ->formatStateUsing(fn (?int $state): string => (string) ($state ?? 0))
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (QueryBuilder $query): string => (string) $query->sum('rooms_sum_total_rooms'))
                    ),

                TextColumn::make('bookings_count')
                    ->label(__('admin.total_bookings'))
                    ->summarize(
                        Summarizer::make()
                            ->using(fn (QueryBuilder $query): string => (string) $query->sum('bookings_count'))
                    ),

                TextColumn::make('reviews_avg_rating')
                    ->label(__('admin.avg_rating'))
                    ->html()
                    ->state(function (Property $record): string {
                        if ($record->reviews_count <= 0) {
                            return '<span class="text-gray-400 dark:text-gray-500">'.__('admin.no_reviews_yet').'</span>';
                        }

                        $rating = number_format((float) $record->reviews_avg_rating, 1);

                        return '<div class="flex items-center gap-1"><span class="text-yellow-400">★</span><span class="font-semibold text-gray-950 dark:text-white">'.$rating.'</span></div>'
                            .'<p class="text-xs text-gray-500 dark:text-gray-400">('.$record->reviews_count.' '.__('admin.reviews').')</p>';
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PropertyStatus $state): string => $state->label())
                    ->color(fn (PropertyStatus $state): string => $state->color())
                    ->toggleable(),
            ])
            ->filters($filters = [
                static::dateRangeFilter('created_at'),

                // direct: true — this report's rows ARE Property records, not Bookings with a
                // ->property relation, so these narrow by the column directly on the row.
                static::propertyTypeFilter($countryId, direct: true),

                static::cityFilter($countryId, direct: true),

                static::partnerFilter($countryId, direct: true),

                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect(PropertyStatus::cases())
                        ->reject(fn (PropertyStatus $s): bool => $s === PropertyStatus::Draft)
                        ->mapWithKeys(fn (PropertyStatus $s) => [$s->value => $s->label()])
                        ->toArray())
                    ->native(false),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            // Required on every IsReportPage table — see PartnerWalletReport.php's
            // ->columnManager(true) comment for why.
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('property-report')
                    ->exports([
                        'id' => ['label' => 'Property ID', 'formatter' => fn (Property $r): string => '#'.str_pad((string) $r->id, 3, '0', STR_PAD_LEFT)],
                        'name' => 'Property',
                        'propertyType.name' => 'Type',
                        'refCity.name' => ['label' => 'City', 'formatter' => fn (Property $r): string => $r->refCity?->name ?? '-'],
                        'partner.user.name' => ['label' => 'Partner', 'formatter' => fn (Property $r): string => $r->partner?->user?->name ?? '-'],
                        'rooms_sum_total_rooms' => ['label' => 'Rooms', 'formatter' => fn (Property $r): string => (string) ($r->rooms_sum_total_rooms ?? 0)],
                        'bookings_count' => 'Total Bookings',
                        'reviews_avg_rating' => ['label' => 'Avg Rating', 'formatter' => fn (Property $r): string => $r->reviews_count > 0 ? number_format((float) $r->reviews_avg_rating, 1).' ('.$r->reviews_count.' '.__('admin.reviews').')' : __('admin.no_reviews_yet')],
                        'status' => ['label' => 'Status', 'formatter' => fn (Property $r): string => $r->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_properties_yet'))
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
