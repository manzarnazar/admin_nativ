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
 * Monthly finance rollup — unlike every other report page, each row is a calendar month, not a
 * single database record. The full formula for every metric lives in
 * App\Services\MonthlyFinanceRollupService (shared with RevenueReport, a 5-metric subset of this
 * same computation) — see that class's docblock rather than duplicating it here.
 */
class FinanceSummaryReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/finance-summary';

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
        return __('admin.report_finance_summary_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_finance_summary_desc');
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
                    ->label(__('admin.total_revenue'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('refunds')
                    ->label(__('admin.total_refunds'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('commission')
                    ->label(__('admin.commission_earned'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('taxes')
                    ->label(__('admin.taxes'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('net_payout')
                    ->label(__('admin.net_payout'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2)),

                TextColumn::make('wallet_credit')
                    ->label(__('admin.partner_wallet_credit'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2))
                    ->toggleable(),

                TextColumn::make('withdrawal')
                    ->label(__('admin.withdrawal'))
                    ->formatStateUsing(fn (float $state) => $currency.number_format($state, 2))
                    ->toggleable(),
            ])
            ->filters([
                static::dateRangeFilter('created_at'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('finance-summary-report')
                    ->exports([
                        'period' => ['label' => 'Period (Month)', 'formatter' => fn (array $r): string => $r['period'] === 'total' ? 'Total' : Carbon::createFromFormat('Y-m', $r['period'])->format('F Y')],
                        'revenue' => ['label' => 'Total Revenue', 'formatter' => fn (array $r): string => $currency.number_format($r['revenue'], 2)],
                        'refunds' => ['label' => 'Total Refunds', 'formatter' => fn (array $r): string => $currency.number_format($r['refunds'], 2)],
                        'commission' => ['label' => 'Commission Earned', 'formatter' => fn (array $r): string => $currency.number_format($r['commission'], 2)],
                        'taxes' => ['label' => 'Taxes', 'formatter' => fn (array $r): string => $currency.number_format($r['taxes'], 2)],
                        'net_payout' => ['label' => 'Net Payout', 'formatter' => fn (array $r): string => $currency.number_format($r['net_payout'], 2)],
                        'wallet_credit' => ['label' => 'Partner Wallet Credit', 'formatter' => fn (array $r): string => $currency.number_format($r['wallet_credit'], 2)],
                        'withdrawal' => ['label' => 'Withdrawal', 'formatter' => fn (array $r): string => $currency.number_format($r['withdrawal'], 2)],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.report_finance_summary_title'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
