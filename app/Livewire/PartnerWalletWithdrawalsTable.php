<?php

namespace App\Livewire;

use App\Enums\WithdrawalStatus;
use App\Models\PropertyWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\PartnerContext;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Support\Facades\Auth;

class PartnerWalletWithdrawalsTable extends TableComponent
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

    public function getHasWithdrawals(): bool
    {
        $wallet = $this->getCurrentWallet();

        return $wallet !== null && $wallet->withdrawalRequests()->exists();
    }

    public function table(Table $table): Table
    {
        $wallet = $this->getCurrentWallet();

        return $table
            ->query(
                WithdrawalRequest::query()
                    ->when(
                        $wallet,
                        fn ($q) => $q->where('property_wallet_id', $wallet->id),
                        fn ($q) => $q->whereRaw('0=1'),
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.requested_date'))
                    ->date('d M, Y')
                    ->description(fn (WithdrawalRequest $record): string => $record->created_at->format('H:i')),

                TextColumn::make('amount')
                    ->label(__('admin.amount'))
                    ->state(fn (WithdrawalRequest $record): string => $record->currency_code.' '.number_format((float) $record->amount, 2))
                    ->weight(FontWeight::Medium),

                TextColumn::make('bank_account_number')
                    ->label(__('admin.bank_account'))
                    ->state(fn (WithdrawalRequest $record): string => $record->masked_account_number)
                    ->description(fn (WithdrawalRequest $record): string => $record->bank_name),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (WithdrawalStatus $state): string => $state->label())
                    ->color(fn (WithdrawalStatus $state): string => $state->color()),

                TextColumn::make('processed_at')
                    ->label(__('admin.processed_at'))
                    ->date('d M, Y')
                    ->placeholder('—'),

                TextColumn::make('admin_notes')
                    ->label(__('admin.admin_notes'))
                    ->placeholder('—')
                    ->limit(40)
                    ->wrap(),
            ])
            ->emptyStateHeading(__('admin.no_withdrawal_requests'))
            ->emptyStateDescription('')
            ->defaultSort('created_at', 'desc');
    }
}
