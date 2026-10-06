<?php

namespace App\Livewire;

use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Filament\Pages\BookingView;
use App\Filament\Partner\Pages\PartnerBookingView;
use App\Models\Booking;
use App\Models\PropertyWallet;
use App\Models\PropertyWalletTransaction;
use App\Models\WithdrawalRequest;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class PropertyWalletTransactionsTable extends TableComponent
{
    public int $propertyId;

    /**
     * all_time|today|this_week|this_month — ignored once customDate is set.
     * Owned by the parent property-detail page (so the Filter row can sit
     * above the stat cards, matching Figma) and passed in via mount().
     */
    public string $datePreset = 'all_time';

    public ?string $customDate = null;

    public function mount(int $propertyId, string $datePreset = 'all_time', ?string $customDate = null): void
    {
        $this->propertyId = $propertyId;
        $this->datePreset = $datePreset;
        $this->customDate = $customDate;
    }

    /**
     * Shared with the parent page's export action so both read the exact
     * same filtered dataset — Filament's export closure only has access to
     * the table() bound to whichever Livewire component is currently
     * handling the request, which a page-level "Exports" button isn't.
     */
    public static function buildQuery(int $propertyId, string $datePreset, ?string $customDate): Builder
    {
        $wallet = PropertyWallet::query()->where('property_id', $propertyId)->first();

        return PropertyWalletTransaction::query()
            ->when(
                $wallet,
                fn ($q) => $q->where('property_wallet_id', $wallet->id),
                fn ($q) => $q->whereRaw('0=1'),
            )
            ->when($customDate, fn ($q) => $q->whereDate('created_at', $customDate))
            ->when(! $customDate, function ($q) use ($datePreset) {
                match ($datePreset) {
                    'today' => $q->whereDate('created_at', now()->toDateString()),
                    'this_week' => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'this_month' => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                    default => null,
                };
            })
            ->latest('created_at');
    }

    /**
     * @return array<string, string|array{label: string, formatter: callable}>
     */
    public static function exportColumns(): array
    {
        return [
            'id' => __('admin.id'),
            'created_at' => __('admin.date_time'),
            'note' => __('admin.transaction'),
            'reference_type' => ['label' => __('admin.type'), 'formatter' => fn (PropertyWalletTransaction $record): string => $record->reference_type->label()],
            'amount' => __('admin.amount'),
            'balance_after' => __('admin.balance_after'),
        ];
    }

    private function getCurrentWallet(): ?PropertyWallet
    {
        return PropertyWallet::query()->with('property.country')->where('property_id', $this->propertyId)->first();
    }

    public function getHasTransactions(): bool
    {
        $wallet = $this->getCurrentWallet();

        return $wallet !== null && $wallet->transactions()->exists();
    }

    public function table(Table $table): Table
    {
        $wallet = $this->getCurrentWallet();
        $symbol = $wallet?->property?->country?->currency_symbol ?? '';
        $isAdmin = Filament::getCurrentPanel()?->getId() === 'admin';

        return $table
            ->query(self::buildQuery($this->propertyId, $this->datePreset, $this->customDate))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id')),

                TextColumn::make('created_at')
                    ->label(__('admin.date_time'))
                    ->date('d M, Y')
                    ->description(fn (PropertyWalletTransaction $record): string => $record->created_at->format('H:i')),

                TextColumn::make('note')
                    ->label(__('admin.transaction'))
                    ->state(fn (PropertyWalletTransaction $record): string => $record->note ?? $record->reference_type->label())
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('reference_id')
                    ->label(__('admin.book_id'))
                    ->html()
                    ->state(function (PropertyWalletTransaction $record) use ($isAdmin): Htmlable {
                        if (! in_array($record->reference_type, [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue], true) || ! $record->reference_id) {
                            return new HtmlString('—');
                        }

                        $booking = Booking::query()->find($record->reference_id);

                        if (! $booking) {
                            return new HtmlString('—');
                        }

                        $url = $isAdmin
                            ? BookingView::getUrl(['record' => $booking->id])
                            : PartnerBookingView::getUrl(['record' => $booking->id]);

                        $icon = Blade::render('<x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5" />');

                        return new HtmlString(
                            '<a href="'.e($url).'" class="inline-flex items-center gap-1 font-medium text-primary-600 hover:underline dark:text-primary-400">#'.e($booking->booking_number).' '.$icon.'</a>'
                        );
                    }),

                TextColumn::make('reference_type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (WalletTransactionReferenceType $state): string => $state->label())
                    ->color(fn (WalletTransactionReferenceType $state): string => $state->color()),

                TextColumn::make('credit')
                    ->label(__('admin.credit'))
                    ->state(fn (PropertyWalletTransaction $record): string => $record->type === WalletTransactionType::Credit ? $symbol.number_format((float) $record->amount, 2) : '—')
                    ->color('success'),

                TextColumn::make('debit')
                    ->label(__('admin.debit'))
                    ->state(fn (PropertyWalletTransaction $record): string => $record->type === WalletTransactionType::Debit ? $symbol.number_format((float) $record->amount, 2) : '—')
                    ->color('danger'),

                TextColumn::make('balance_after')
                    ->label(__('admin.balance_after'))
                    ->state(fn (PropertyWalletTransaction $record): string => $symbol.number_format((float) $record->balance_after, 2)),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(function (PropertyWalletTransaction $record): string {
                        if ($record->type === WalletTransactionType::Credit) {
                            return 'credit';
                        }

                        if ($record->reference_type === WalletTransactionReferenceType::Withdrawal && $record->reference_id) {
                            $status = WithdrawalRequest::query()->where('id', $record->reference_id)->toBase()->value('status');

                            return $status ?? 'debit';
                        }

                        return 'debit';
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit' => __('admin.completed'),
                        WithdrawalStatus::Pending->value => __('admin.pending'),
                        WithdrawalStatus::Approved->value => __('admin.completed'),
                        WithdrawalStatus::Rejected->value => __('admin.rejected'),
                        default => __('admin.completed'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit', WithdrawalStatus::Approved->value => 'success',
                        WithdrawalStatus::Pending->value => 'warning',
                        WithdrawalStatus::Rejected->value => 'danger',
                        default => 'success',
                    }),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50]);
    }
}
