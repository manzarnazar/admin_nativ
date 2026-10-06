<?php

namespace App\Livewire;

use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Filament\Partner\Pages\PartnerBookingView;
use App\Models\PropertyWallet;
use App\Models\PropertyWalletTransaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\PartnerContext;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Support\Facades\Auth;

class PartnerWalletTransactionsTable extends TableComponent
{
    private function getCurrentWallet(): ?PropertyWallet
    {
        /** @var User $user */
        $user = Auth::user();
        $partner = $user->partner;

        if (! $partner) {
            return null;
        }

        $countryId = PartnerContext::currentCountryId($partner);
        $propertyId = $countryId ? PartnerContext::currentPropertyId($partner, $countryId) : null;

        if (! $propertyId) {
            return null;
        }

        return PropertyWallet::query()->where('property_id', $propertyId)->first();
    }

    public function getHasTransactions(): bool
    {
        $wallet = $this->getCurrentWallet();

        return $wallet !== null && $wallet->transactions()->exists();
    }

    public function table(Table $table): Table
    {
        $wallet = $this->getCurrentWallet();

        return $table
            ->query(
                PropertyWalletTransaction::query()
                    ->with(['wallet', 'booking:id,booking_number'])
                    ->when(
                        $wallet,
                        fn ($q) => $q->where('property_wallet_id', $wallet->id),
                        fn ($q) => $q->whereRaw('0=1'),
                    )
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin.date'))
                    ->date('d M, Y')
                    ->description(fn (PropertyWalletTransaction $record): string => $record->created_at->format('H:i')),

                TextColumn::make('note')
                    ->label(__('admin.transaction'))
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('reference_id')
                    ->label(__('admin.book_id'))
                    ->state(function (PropertyWalletTransaction $record): string {
                        $bookingTypes = [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue];
                        if (in_array($record->reference_type, $bookingTypes, true)) {
                            return $record->booking?->booking_number ?? '—';
                        }

                        return '—';
                    })
                    ->url(function (PropertyWalletTransaction $record): ?string {
                        $bookingTypes = [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue];

                        if (in_array($record->reference_type, $bookingTypes, true) && $record->reference_id) {
                            return PartnerBookingView::getUrl(['record' => $record->reference_id]);
                        }

                        return null;
                    })
                    ->openUrlInNewTab()
                    ->color(fn (PropertyWalletTransaction $record): string => in_array($record->reference_type, [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue], true) ? 'primary' : 'gray'),

                TextColumn::make('reference_type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (WalletTransactionReferenceType $state): string => $state->label())
                    ->color(fn (WalletTransactionReferenceType $state): string => $state->color()),

                TextColumn::make('type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (WalletTransactionType $state): string => $state->label())
                    ->color(fn (WalletTransactionType $state): string => $state->color()),

                TextColumn::make('amount')
                    ->label(__('admin.amount'))
                    ->state(function (PropertyWalletTransaction $record): string {
                        $wallet = $this->getCurrentWallet();
                        $symbol = $wallet?->property?->country?->currency_symbol ?? '';
                        $prefix = $record->type === WalletTransactionType::Credit ? '+' : '-';

                        return $prefix.$symbol.number_format((float) $record->amount, 2);
                    })
                    ->color(fn (PropertyWalletTransaction $record): string => $record->type === WalletTransactionType::Credit ? 'success' : 'danger'),

                TextColumn::make('balance_after')
                    ->label(__('admin.balance_after'))
                    ->state(function (PropertyWalletTransaction $record): string {
                        $wallet = $this->getCurrentWallet();
                        $symbol = $wallet?->property?->country?->currency_symbol ?? '';

                        return $symbol.number_format((float) $record->balance_after, 2);
                    })
                    ->color('gray'),

                TextColumn::make('tx_status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(function (PropertyWalletTransaction $record): string {
                        if ($record->type === WalletTransactionType::Credit) {
                            return 'completed';
                        }

                        if ($record->reference_type === WalletTransactionReferenceType::Withdrawal && $record->reference_id) {
                            $status = WithdrawalRequest::query()
                                ->where('id', $record->reference_id)
                                ->toBase()
                                ->value('status');

                            return $status ?? 'debit';
                        }

                        return 'debit';
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => __('admin.completed'),
                        WithdrawalStatus::Pending->value => __('admin.pending'),
                        WithdrawalStatus::Approved->value => __('admin.approved'),
                        WithdrawalStatus::Rejected->value => __('admin.rejected'),
                        default => __('admin.debit'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        WithdrawalStatus::Pending->value => 'warning',
                        WithdrawalStatus::Approved->value => 'success',
                        WithdrawalStatus::Rejected->value => 'danger',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading(__('admin.no_transactions_yet'))
            ->emptyStateDescription('')
            ->defaultSort('created_at', 'desc');
    }
}
