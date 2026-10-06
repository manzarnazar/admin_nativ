<?php

namespace App\Filament\Pages;

use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Booking;
use App\Models\Country;
use App\Models\PropertyWalletTransaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PartnerWalletReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/partner-wallet';

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
        return __('admin.report_partner_wallet_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_partner_wallet_desc');
    }

    /**
     * A Booking ID only exists for a BookingRevenue/CancellationRevenue transaction — a
     * Withdrawal-type row's reference_id points at a WithdrawalRequest, not a Booking.
     */
    private function resolveBookingLink(PropertyWalletTransaction $record): Htmlable
    {
        if (! in_array($record->reference_type, [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue], true) || ! $record->reference_id) {
            return new HtmlString('-');
        }

        $booking = Booking::query()->find($record->reference_id);

        if (! $booking) {
            return new HtmlString('-');
        }

        return static::linkedNameWithIcon(
            BookingView::getUrl(['record' => $booking->id]),
            '#'.$booking->booking_number,
        );
    }

    /**
     * Status shown for a transaction row — mirrors PropertyWalletTransactionsTable's own
     * derivation exactly, so this report and the per-property Wallet tab can never disagree.
     * Every Credit is settled the instant it's recorded, and so is a Debit for anything other
     * than a Withdrawal — only a Withdrawal-type Debit carries a real pending/rejected
     * lifecycle, read from its WithdrawalRequest.
     */
    private function resolveStatusKey(PropertyWalletTransaction $record): string
    {
        if ($record->type === WalletTransactionType::Credit) {
            return 'credit';
        }

        if ($record->reference_type === WalletTransactionReferenceType::Withdrawal && $record->reference_id) {
            $status = WithdrawalRequest::query()->where('id', $record->reference_id)->toBase()->value('status');

            return $status ?? 'debit';
        }

        return 'debit';
    }

    private function resolveStatusLabel(string $key): string
    {
        return match ($key) {
            'credit' => __('admin.completed'),
            WithdrawalStatus::Pending->value => __('admin.pending'),
            WithdrawalStatus::Approved->value => __('admin.completed'),
            WithdrawalStatus::Rejected->value => __('admin.rejected'),
            default => __('admin.completed'),
        };
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = Country::query()->where('id', $user->current_country_id)->value('currency_symbol') ?? '$';
        $countryId = $user->current_country_id;

        $query = PropertyWalletTransaction::query()
            ->with(['wallet.property.partner.user', 'booking'])
            ->whereHas('wallet.property', fn (Builder $q) => $q->where('country_id', $countryId))
            ->latest('created_at');

        return $table
            ->query($query)
            ->searchPlaceholder(__('admin.search_partners'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.transaction'))
                    ->state(fn (PropertyWalletTransaction $record): string => 'PTW-'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT))
                    ->description(fn (PropertyWalletTransaction $record): string => $record->created_at->format('d M, Y')),

                TextColumn::make('wallet.property.partner.user.name')
                    ->label(__('admin.partner_name'))
                    ->state(fn (PropertyWalletTransaction $record): string => $record->wallet?->property?->partner?->user?->name ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'wallet.property.partner.user',
                            fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                        );
                    }),

                TextColumn::make('type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (WalletTransactionType $state): string => $state->label())
                    ->color(fn (WalletTransactionType $state): string => $state->color())
                    ->icon(fn (WalletTransactionType $state): Htmlable => $state === WalletTransactionType::Credit
                        ? svg('others.trendup2', 'h-3 w-3')
                        : svg('others.trenddown', 'h-3 w-3'))
                    ->toggleable(),

                static::moneyColumn('amount', __('admin.amount'), $currency)
                    ->toggleable(),

                TextColumn::make('reference_id')
                    ->label(__('admin.book_id'))
                    ->html()
                    ->state(fn (PropertyWalletTransaction $record): Htmlable => $this->resolveBookingLink($record))
                    ->toggleable(),

                TextColumn::make('note')
                    ->label(__('admin.description'))
                    ->state(fn (PropertyWalletTransaction $record): string => $record->note ?? $record->reference_type->label())
                    ->limit(30)
                    ->wrap()
                    ->toggleable(),

                static::moneyColumn('balance_after', __('admin.balance_after'), $currency)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(fn (PropertyWalletTransaction $record): string => $this->resolveStatusKey($record))
                    ->formatStateUsing(fn (string $state): string => $this->resolveStatusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'credit', WithdrawalStatus::Approved->value => 'success',
                        WithdrawalStatus::Pending->value => 'warning',
                        WithdrawalStatus::Rejected->value => 'danger',
                        default => 'success',
                    })
                    ->toggleable(),
            ])
            ->filters([
                static::dateRangeFilter('created_at'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->deferFilters(false)
            // Required on every IsReportPage table, even one with no toggleable columns:
            // Filament's own $hasFiltersTrigger never counts AboveContentCollapsible as
            // needing a toolbar trigger (see vendor/filament/tables/resources/views/
            // index.blade.php:106-115), so the whole toolbar row hosting the Columns
            // button AND this page's injected filters trigger (inline-filters-trigger
            // .blade.php) only renders when $hasColumnManager is true. Without this, a
            // report whose columns are all non-toggleable silently loses its filters UI
            // entirely — not just visually broken, absent from the rendered HTML.
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('partner-wallet-report')
                    ->exports([
                        'id' => ['label' => 'Transaction', 'formatter' => fn (PropertyWalletTransaction $r): string => 'PTW-'.str_pad((string) $r->id, 3, '0', STR_PAD_LEFT)],
                        'wallet.property.partner.user.name' => 'Partner Name',
                        'type' => ['label' => 'Type', 'formatter' => fn (PropertyWalletTransaction $r): string => $r->type->label()],
                        'amount' => ['label' => 'Amount', 'formatter' => fn (PropertyWalletTransaction $r): string => $currency.number_format((float) $r->amount, 2)],
                        'booking.booking_number' => ['label' => 'Booking Ref', 'formatter' => fn (PropertyWalletTransaction $r): string => in_array($r->reference_type, [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue], true) ? '#'.($r->booking?->booking_number ?? '-') : '-'],
                        'note' => ['label' => 'Description', 'formatter' => fn (PropertyWalletTransaction $r): string => $r->note ?? $r->reference_type->label()],
                        'balance_after' => ['label' => 'Balance After', 'formatter' => fn (PropertyWalletTransaction $r): string => $currency.number_format((float) $r->balance_after, 2)],
                        'status' => ['label' => 'Status', 'formatter' => fn (PropertyWalletTransaction $r): string => $this->resolveStatusLabel($this->resolveStatusKey($r))],
                        'created_at' => ['label' => 'Date', 'formatter' => fn (PropertyWalletTransaction $r): string => $r->created_at->format('d M, Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_transactions_yet'))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
