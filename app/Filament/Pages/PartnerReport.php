<?php

namespace App\Filament\Pages;

use App\Enums\PartnerVerificationStatus;
use App\Enums\WithdrawalStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\City;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
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

/**
 * Money columns on this report (see the private methods below for the full formula on each):
 *
 * - Pending Payout: pendingPayoutSubquery() — SUM(PropertyWallet.balance) across the partner's
 *   properties in this country. This is current wallet balance, not just amounts already
 *   requested for withdrawal — a Pending WithdrawalRequest doesn't debit balance yet, only an
 *   Approved one does (via PropertyWalletService::approveWithdrawal()).
 *
 * - Total Earnings: resolveTotalEarningsTotal() — Pending Payout (current balance) + everything
 *   already paid out via an Approved withdrawal. The full lifetime amount ever credited to the
 *   partner's wallets, net of commission (only the post-commission share is ever credited, never
 *   the gross booking amount).
 *
 * - Commission %: the partner's commissionOverrides entry for their own property_type_id if one
 *   exists, else the country's default CommissionRate. A partner is locked to a single property
 *   type, so at most one override is ever relevant — it fully replaces the default rather than
 *   partially adjusting it.
 */
class PartnerReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/partner';

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
        return __('admin.report_partner_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_partner_desc');
    }

    /**
     * Pending Payout = the partner's current wallet balance across their properties in this
     * country — not just amounts already requested for withdrawal. A WithdrawalRequest in
     * Pending status doesn't debit PropertyWallet.balance yet (only an Approved one does, via
     * PropertyWalletService::approveWithdrawal()), so balance already reflects "earned but not
     * yet paid out" regardless of whether the partner has even asked for it yet.
     */
    private function pendingPayoutSubquery(int $countryId, ?int $cityId): Builder
    {
        return PropertyWallet::query()
            ->selectRaw('COALESCE(SUM(balance), 0)')
            ->join('properties', 'properties.id', '=', 'property_wallets.property_id')
            ->whereColumn('properties.partner_id', 'partners.id')
            ->where('properties.country_id', $countryId)
            ->when($cityId, fn (Builder $q) => $q->where('properties.ref_city_id', $cityId));
    }

    /**
     * Total Earnings = Pending Payout (current balance) + everything already paid out via an
     * Approved withdrawal — the full lifetime amount ever credited to the partner's wallets,
     * net of commission (PropertyWalletService only ever credits calculatePartnerCredit(), the
     * post-commission share — never the gross booking amount).
     */
    private function paidOutSubquery(int $countryId, ?int $cityId): Builder
    {
        return WithdrawalRequest::query()
            ->selectRaw('COALESCE(SUM(withdrawal_requests.amount), 0)')
            ->join('property_wallets', 'property_wallets.id', '=', 'withdrawal_requests.property_wallet_id')
            ->join('properties', 'properties.id', '=', 'property_wallets.property_id')
            ->whereColumn('properties.partner_id', 'partners.id')
            ->where('properties.country_id', $countryId)
            ->when($cityId, fn (Builder $q) => $q->where('properties.ref_city_id', $cityId))
            ->where('withdrawal_requests.status', WithdrawalStatus::Approved);
    }

    /**
     * properties_count/pending_payout/paid_out are withCount()/selectSub() aliases, not real
     * columns — same problem as Refund in BookingReport: a plain SQL sum() can't reference a
     * SELECT-list alias once it replaces that same SELECT clause. The "Total" row for each is
     * computed independently against the filtered set of partner IDs instead.
     */
    private function resolvePropertiesCountTotal(QueryBuilder $query, int $countryId, ?int $cityId): int
    {
        $partnerIds = $query->pluck('id');

        if ($partnerIds->isEmpty()) {
            return 0;
        }

        return Property::query()
            ->whereIn('partner_id', $partnerIds)
            ->where('country_id', $countryId)
            ->when($cityId, fn (Builder $q) => $q->where('ref_city_id', $cityId))
            ->count();
    }

    private function resolvePendingPayoutTotal(QueryBuilder $query, int $countryId, ?int $cityId, string $currency): string
    {
        $partnerIds = $query->pluck('id');

        if ($partnerIds->isEmpty()) {
            return $currency.'0.00';
        }

        $total = PropertyWallet::query()
            ->whereHas('property', fn (Builder $q) => $q->whereIn('partner_id', $partnerIds)
                ->where('country_id', $countryId)
                ->when($cityId, fn (Builder $inner) => $inner->where('ref_city_id', $cityId)))
            ->sum('balance');

        return $currency.number_format((float) $total, 2);
    }

    private function resolveTotalEarningsTotal(QueryBuilder $query, int $countryId, ?int $cityId, string $currency): string
    {
        $partnerIds = $query->pluck('id');

        if ($partnerIds->isEmpty()) {
            return $currency.'0.00';
        }

        $pending = PropertyWallet::query()
            ->whereHas('property', fn (Builder $q) => $q->whereIn('partner_id', $partnerIds)
                ->where('country_id', $countryId)
                ->when($cityId, fn (Builder $inner) => $inner->where('ref_city_id', $cityId)))
            ->sum('balance');

        $paidOut = WithdrawalRequest::query()
            ->where('status', WithdrawalStatus::Approved)
            ->whereHas('wallet.property', fn (Builder $q) => $q->whereIn('partner_id', $partnerIds)
                ->where('country_id', $countryId)
                ->when($cityId, fn (Builder $inner) => $inner->where('ref_city_id', $cityId)))
            ->sum('amount');

        return $currency.number_format((float) $pending + (float) $paidOut, 2);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = Country::query()->where('id', $user->current_country_id)->value('currency_symbol') ?? '$';
        $countryId = $user->current_country_id;

        // Properties/Total Earnings/Pending Payout are aggregates across the partner's
        // properties — read live rather than only at row-inclusion time, so picking a City
        // narrows these numbers down to that city's slice too, not just which partners appear.
        $cityId = $this->getTableFilterState('city')['city_id'] ?? null;

        $countryDefaultRate = (float) (CommissionRate::query()
            ->where('country_id', $countryId)
            ->whereNull('property_type_id')
            ->value('rate') ?? 0);

        $query = Partner::query()
            ->whereHas('user')
            // Only partners approved at least once belong on this report — Pending/Rejected/
            // CorrectionRequested/Resubmission partners live in the /partner-verification queue
            // instead, matching AllPartnersManage.php's exact same restriction.
            ->whereIn('verification_status', [PartnerVerificationStatus::Approved, PartnerVerificationStatus::Suspended])
            ->whereHas('countries', fn (Builder $q) => $q->where('countries.id', $countryId))
            ->with([
                'user',
                'commissionOverrides' => fn ($q) => $q->where('country_id', $countryId),
            ])
            ->withCount(['properties as properties_count' => fn (Builder $q) => $q
                ->where('country_id', $countryId)
                ->when($cityId, fn (Builder $inner) => $inner->where('ref_city_id', $cityId))])
            ->selectSub($this->pendingPayoutSubquery($countryId, $cityId), 'pending_payout')
            ->selectSub($this->paidOutSubquery($countryId, $cityId), 'paid_out');

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_partners'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.partner_id'))
                    ->state(fn (Partner $record): Htmlable => static::linkedNameWithIcon(
                        AllPartnersDetail::getUrl().'?partnerId='.$record->id,
                        '#'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT),
                    )),

                TextColumn::make('user.name')
                    ->label(__('admin.partner_name'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    }),

                TextColumn::make('user.email')
                    ->label(__('admin.contact_info'))
                    ->state(fn (Partner $record): string => DemoMode::maskEmail($record->user?->email) ?? '-')
                    ->description(fn (Partner $record): string => $record->user?->phone
                        ? ltrim(($record->user->dial_code ?? '').' '.(DemoMode::maskPhone($record->user->phone) ?? ''))
                        : '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', fn (Builder $q) => $q->where('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin.registered_on'))
                    ->date('d M Y'),

                TextColumn::make('properties_count')
                    ->label(__('admin.properties'))
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): int => $this->resolvePropertiesCountTotal($query, $countryId, $cityId))
                    ),

                TextColumn::make('total_earnings')
                    ->label(__('admin.total_earnings'))
                    ->getStateUsing(fn (Partner $record): string => $currency.number_format((float) $record->pending_payout + (float) $record->paid_out, 2))
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): string => $this->resolveTotalEarningsTotal($query, $countryId, $cityId, $currency))
                    ),

                TextColumn::make('pending_payout')
                    ->label(__('admin.pending_payout'))
                    ->formatStateUsing(fn (?string $state): string => $currency.number_format((float) ($state ?? 0), 2))
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): string => $this->resolvePendingPayoutTotal($query, $countryId, $cityId, $currency))
                    ),

                TextColumn::make('commission')
                    ->label(__('admin.commission_percent'))
                    // A partner is locked to a single property_type_id (every property they
                    // create inherits it — see PartnerPropertyCreate.php:344), so at most one
                    // override is ever relevant to them. When it exists it's not a partial
                    // override alongside the country default — it fully replaces it, so the
                    // default is never actually shown next to it.
                    ->state(function (Partner $record) use ($countryDefaultRate): string {
                        $override = $record->commissionOverrides->firstWhere('property_type_id', $record->property_type_id);

                        return ($override?->rate ?? $countryDefaultRate).'%';
                    })
                    ->description(fn (Partner $record): ?string => $record->commissionOverrides
                        ->firstWhere('property_type_id', $record->property_type_id)
                        ? __('admin.overridden')
                        : null)
                    ->toggleable(),

                TextColumn::make('verification_status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PartnerVerificationStatus $state): string => match ($state) {
                        PartnerVerificationStatus::Approved => __('admin.active'),
                        PartnerVerificationStatus::Suspended => __('admin.suspended'),
                        default => __('admin.inactive'),
                    })
                    ->color(fn (PartnerVerificationStatus $state): string => match ($state) {
                        PartnerVerificationStatus::Approved => 'success',
                        PartnerVerificationStatus::Suspended => 'danger',
                        default => 'warning',
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
                        fn (Builder $q, $value) => $q->whereHas('properties', fn (Builder $pq) => $pq->where('country_id', $countryId)->where('ref_city_id', $value))
                    ))
                    ->indicateUsing(fn (array $data): array => filled($data['city_id'] ?? null)
                        ? [Indicator::make(__('admin.city').': '.(City::where('ref_city_id', $data['city_id'])->value('name') ?? $data['city_id']))->removeField('city_id')]
                        : []),

                SelectFilter::make('verification_status')
                    ->label(__('admin.status'))
                    ->options([
                        PartnerVerificationStatus::Approved->value => __('admin.active'),
                        PartnerVerificationStatus::Suspended->value => __('admin.suspended'),
                    ])
                    ->native(false),

                Filter::make('sort_by')
                    ->label(__('admin.sort_by'))
                    ->schema([
                        Select::make('sort_by')
                            ->label(__('admin.sort_by'))
                            ->options([
                                'highest_property' => __('admin.highest_property'),
                                'lowest_property' => __('admin.lowest_property'),
                                'highest_earning' => __('admin.highest_earning'),
                                'lowest_earning' => __('admin.lowest_earning'),
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // reorder() clears the table's default "created_at desc" sort first —
                        // otherwise it stays as the primary ORDER BY key (timestamps essentially
                        // never tie) and this filter's own ordering never visibly takes effect.
                        return match ($data['sort_by'] ?? null) {
                            'highest_property' => $query->reorder()->orderByDesc('properties_count'),
                            'lowest_property' => $query->reorder()->orderBy('properties_count'),
                            'highest_earning' => $query->reorder()->orderByRaw('(pending_payout + paid_out) desc'),
                            'lowest_earning' => $query->reorder()->orderByRaw('(pending_payout + paid_out) asc'),
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
                    ->filename('partner-report')
                    ->exports([
                        'id' => ['label' => 'Partner ID', 'formatter' => fn (Partner $r): string => '#'.str_pad((string) $r->id, 3, '0', STR_PAD_LEFT)],
                        'user.name' => 'Partner Name',
                        'user.email' => 'Email',
                        'user.phone' => 'Phone',
                        'created_at' => ['label' => 'Registered On', 'formatter' => fn (Partner $r): string => $r->created_at->format('d M Y')],
                        'properties_count' => 'Properties',
                        'total_earnings' => ['label' => 'Total Earnings', 'formatter' => fn (Partner $r) => $currency.number_format((float) $r->pending_payout + (float) $r->paid_out, 2)],
                        'pending_payout' => ['label' => 'Pending Payout', 'formatter' => fn (Partner $r) => $currency.number_format((float) ($r->pending_payout ?? 0), 2)],
                        'verification_status' => ['label' => 'Status', 'formatter' => fn (Partner $r): string => match ($r->verification_status) {
                            PartnerVerificationStatus::Approved => __('admin.active'),
                            PartnerVerificationStatus::Suspended => __('admin.suspended'),
                            default => __('admin.inactive'),
                        }],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_partners_yet'))
            ->emptyStateIcon('heroicon-o-users')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            // One "Total" row across all filtered results, not a separate per-page row too.
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
