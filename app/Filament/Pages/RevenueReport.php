<?php

namespace App\Filament\Pages;

use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Country;
use App\Models\User;
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
use Illuminate\Support\Collection;

/**
 * Monthly rollup — a 5-column subset of FinanceSummaryReport's 7-column breakdown, both backed
 * by the exact same App\Services\MonthlyFinanceRollupService so the two reports can never
 * disagree about a given month's numbers (Total Booking Value/Platform Commission/Taxes/Refunds/
 * Net Revenue here map to that service's revenue/commission/taxes/refunds/net_payout keys). See
 * that service's docblock for the full formula behind each figure.
 *
 * Net Revenue is Total Revenue − Commission − Taxes − Refunds — a "what's left in the pool for
 * partners" figure, NOT the platform's own earned revenue (that's Commission alone). Kept as-is
 * to match this report's Figma design.
 */
class RevenueReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/revenue';

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
        return __('admin.report_revenue_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_revenue_desc');
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = $this->getCurrencySymbol();
        $countryId = $user->current_country_id;
        $rollup = app(MonthlyFinanceRollupService::class);

        return $table
            ->records(function (array $filters) use ($countryId, $rollup): Collection {
                $range = $rollup->resolveDateRange($filters);
                $rows = $rollup->monthlyRows($countryId, $range);

                $records = collect($rows)->map(fn (array $row, string $ym): array => array_merge(['period' => $ym], $row))
                    ->values();

                if ($records->isNotEmpty()) {
                    $records->push(array_merge(['period' => 'total'], $rollup->totalsRow($rows)));
                }

                return $records;
            })
            ->paginated(false)
            ->recordClasses(fn (array $record): ?string => $record['period'] === 'total' ? 'fi-finance-summary-total-row' : null)
            ->columns([
                TextColumn::make('period')
                    ->label(__('admin.period_month'))
                    ->weight(fn (array $record): ?string => $record['period'] === 'total' ? 'bold' : null)
                    ->formatStateUsing(fn (string $state): string => $state === 'total'
                        ? __('admin.total')
                        : Carbon::createFromFormat('Y-m', $state)->format('F Y')),

                TextColumn::make('revenue')
                    ->label(__('admin.total_booking_value'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('commission')
                    ->label(__('admin.platform_commission'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('taxes')
                    ->label(__('admin.taxes'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('refunds')
                    ->label(__('admin.refunds'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('net_payout')
                    ->label(__('admin.net_revenue'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),
            ])
            ->filters([
                static::dateRangeFilter('created_at'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('revenue-report')
                    ->exports([
                        'period' => ['label' => 'Period (Month)', 'formatter' => fn (array $r): string => $r['period'] === 'total' ? 'Total' : Carbon::createFromFormat('Y-m', $r['period'])->format('F Y')],
                        'revenue' => ['label' => 'Total Booking Value', 'formatter' => fn (array $r): string => $currency.number_format($r['revenue'], 2)],
                        'commission' => ['label' => 'Platform Commission', 'formatter' => fn (array $r): string => $currency.number_format($r['commission'], 2)],
                        'taxes' => ['label' => 'Taxes', 'formatter' => fn (array $r): string => $currency.number_format($r['taxes'], 2)],
                        'refunds' => ['label' => 'Refunds', 'formatter' => fn (array $r): string => $currency.number_format($r['refunds'], 2)],
                        'net_payout' => ['label' => 'Net Revenue', 'formatter' => fn (array $r): string => $currency.number_format($r['net_payout'], 2)],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.report_revenue_title'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
