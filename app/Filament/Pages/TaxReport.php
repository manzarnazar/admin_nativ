<?php

namespace App\Filament\Pages;

use App\Enums\TaxType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\MonthlyFinanceRollupService;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Row = one individual tax line item, not one booking — bookings.tax_details is a JSON array
 * (App\Actions\CalculatePricingAction builds it as {name, type, rate, amount} per active Tax
 * rule applied at booking time), so a booking with 2 taxes contributes 2 rows here. No Eloquent
 * relation can represent that grain, so — like FinanceSummaryReport/RevenueReport — this uses
 * ->records() over a flattened Collection instead of ->query(), reusing
 * MonthlyFinanceRollupService::resolveDateRange() to parse the date filter for the same reason
 * those two do (a records()-backed table's own filter ->query() closures never run).
 *
 * Some older/seeded rows store tax_details with only {name, amount} (no type/rate) — Tax
 * Type/Tax Value read those keys defensively and show "-" rather than assume they exist, same
 * defensive pattern already used in resources/views/invoices/booking-invoice.blade.php.
 *
 * Shows every booking status that has tax_details, not just "paid & valid" (unlike
 * CommissionReport) — tax is calculated and shown to the customer at booking time regardless of
 * what happens to the booking afterward, so excluding Cancelled/Pending here would hide real
 * tax figures the customer actually saw.
 *
 * Two separate tax columns, deliberately not the same number:
 *   - Tax Charged   — the full, nominal tax_details amount, unconditionally (what the customer
 *                     was shown/disclosed at booking time).
 *   - Tax Collected — the same amount scaled by CommissionService::resolveOnlineCollectionRatio(),
 *                     i.e. what the platform actually holds right now. A Manual/pay-at-property
 *                     portion never reaches the platform, and a refunded portion no longer sits
 *                     in its account, so "Tax Charged" alone overstates what admin can actually
 *                     remit whenever a booking has any cash or refunded component — this is the
 *                     figure to use for real tax filing, not the charged figure.
 */
class TaxReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/tax';

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
        return __('admin.report_tax_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_tax_desc');
    }

    /**
     * @return array{name: string, type: ?string, rate: ?float, amount: float}
     */
    private function normalizeTaxLine(array $tax): array
    {
        return [
            'name' => (string) ($tax['name'] ?? '-'),
            'type' => isset($tax['type']) ? (string) $tax['type'] : null,
            'rate' => isset($tax['rate']) ? (float) $tax['rate'] : null,
            'amount' => (float) ($tax['amount'] ?? 0),
        ];
    }

    private function formatTaxValue(?string $type, ?float $rate, string $currency): string
    {
        if ($rate === null) {
            return '-';
        }

        if ($type === TaxType::Fixed->value) {
            return $currency.number_format($rate, 2);
        }

        // Percentage — trim trailing zeros (18.00 -> "18", 7.50 -> "7.5").
        return rtrim(rtrim(number_format($rate, 2), '0'), '.').'%';
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = Country::query()->where('id', $user->current_country_id)->value('currency_symbol') ?? '$';
        $countryId = $user->current_country_id;
        $rollup = app(MonthlyFinanceRollupService::class);

        return $table
            ->records(function (array $filters) use ($countryId, $currency, $rollup): Collection {
                $range = $rollup->resolveDateRange($filters);
                $search = mb_strtolower((string) $this->getTableSearch());
                $commissionService = app(CommissionService::class);

                $bookings = Booking::query()
                    ->with(['property.propertyType', 'property.country'])
                    ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId))
                    ->whereNotNull('tax_details')
                    ->when($range, fn (Builder $q) => $q->whereBetween('created_at', [$range[0], $range[1]]))
                    ->get();

                $records = $bookings
                    ->flatMap(function (Booking $booking) use ($currency, $search, $commissionService): array {
                        // Same fraction applies to every tax line on this booking — a pay-at-property
                        // (Manual) or refunded portion never reached the platform, so tax nominally
                        // charged on it was never actually collected either.
                        $collectionRatio = $commissionService->resolveOnlineCollectionRatio($booking);

                        return collect($booking->tax_details ?? [])
                            ->map(fn (array $tax): array => $this->normalizeTaxLine($tax))
                            ->filter(fn (array $tax): bool => $tax['name'] !== '-')
                            ->when(filled($search), fn (Collection $items) => $items->filter(
                                fn (array $tax): bool => str_contains(mb_strtolower($tax['name']), $search)
                            ))
                            ->map(fn (array $tax): array => [
                                'row_type' => 'line',
                                'booking_id' => $booking->id,
                                'booking_number' => $booking->booking_number,
                                'property_type' => $booking->property?->propertyType?->name ?? '-',
                                'tax_name' => $tax['name'],
                                'tax_type' => $tax['type'],
                                'tax_value' => $this->formatTaxValue($tax['type'], $tax['rate'], $currency),
                                'tax_amount' => $tax['amount'],
                                'tax_collected' => round($tax['amount'] * $collectionRatio, 2),
                                'country' => $booking->property?->country?->name ?? '-',
                                'applied_date' => $booking->created_at,
                            ])
                            ->values()
                            ->all();
                    })
                    ->values();

                if ($records->isNotEmpty()) {
                    $records->push([
                        'row_type' => 'total',
                        'booking_id' => null,
                        'booking_number' => null,
                        'property_type' => null,
                        'tax_name' => null,
                        'tax_type' => null,
                        'tax_value' => null,
                        'tax_amount' => $records->sum('tax_amount'),
                        'tax_collected' => $records->sum('tax_collected'),
                        'country' => null,
                        'applied_date' => null,
                    ]);
                }

                return $records;
            })
            ->paginated(false)
            ->recordClasses(fn (array $record): ?string => $record['row_type'] === 'total' ? 'fi-finance-summary-total-row' : null)
            ->columns([
                TextColumn::make('booking_number')
                    ->label(__('admin.booking_id'))
                    ->html()
                    ->state(function (array $record): Htmlable {
                        if ($record['row_type'] === 'total') {
                            return new HtmlString('<span class="font-bold">'.__('admin.total').'</span>');
                        }

                        return static::linkedNameWithIcon(
                            BookingView::getUrl(['record' => $record['booking_id']]),
                            '#'.$record['booking_number'],
                        );
                    }),

                TextColumn::make('property_type')
                    ->label(__('admin.property_type'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (array $record): ?string => $record['property_type']),

                TextColumn::make('tax_name')
                    ->label(__('admin.tax_name'))
                    ->state(fn (array $record): ?string => $record['tax_name']),

                TextColumn::make('tax_type')
                    ->label(__('admin.tax_type'))
                    ->state(fn (array $record): string => TaxType::tryFrom((string) $record['tax_type'])?->label() ?? '-')
                    ->toggleable(),

                TextColumn::make('tax_value')
                    ->label(__('admin.tax_value'))
                    ->state(fn (array $record): ?string => $record['tax_value'])
                    ->toggleable(),

                TextColumn::make('tax_amount')
                    ->label(__('admin.tax_amount'))
                    ->weight(fn (array $record): ?string => $record['row_type'] === 'total' ? 'bold' : null)
                    ->formatStateUsing(fn (float $state): string => $currency.number_format($state, 2)),

                TextColumn::make('tax_collected')
                    ->label(__('admin.tax_collected'))
                    ->weight(fn (array $record): ?string => $record['row_type'] === 'total' ? 'bold' : null)
                    ->color('success')
                    ->formatStateUsing(fn (float $state): string => $currency.number_format($state, 2)),

                TextColumn::make('country')
                    ->label(__('admin.country'))
                    ->state(fn (array $record): ?string => $record['country'])
                    ->toggleable(),

                TextColumn::make('applied_date')
                    ->label(__('admin.applied_date'))
                    ->state(fn (array $record): ?string => $record['applied_date']?->format('d M, Y'))
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
                    ->filename('tax-report')
                    ->exports([
                        'booking_number' => ['label' => 'Booking ID', 'formatter' => fn (array $r): string => $r['row_type'] === 'total' ? 'Total' : '#'.$r['booking_number']],
                        'property_type' => 'Property Type',
                        'tax_name' => 'Tax Name',
                        'tax_type' => ['label' => 'Tax Type', 'formatter' => fn (array $r): string => TaxType::tryFrom((string) $r['tax_type'])?->label() ?? '-'],
                        'tax_value' => 'Tax Value',
                        'tax_amount' => ['label' => 'Tax Charged', 'formatter' => fn (array $r): string => $currency.number_format((float) $r['tax_amount'], 2)],
                        'tax_collected' => ['label' => 'Tax Collected', 'formatter' => fn (array $r): string => $currency.number_format((float) $r['tax_collected'], 2)],
                        'country' => 'Country',
                        'applied_date' => ['label' => 'Applied Date', 'formatter' => fn (array $r): string => $r['applied_date']?->format('d M, Y') ?? '-'],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_transactions_yet'))
            ->emptyStateIcon('heroicon-o-receipt-percent');
    }
}
