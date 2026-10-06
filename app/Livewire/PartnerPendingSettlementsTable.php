<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Filament\Partner\Pages\PartnerBookingView;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Services\CommissionService;
use App\Support\PartnerContext;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PartnerPendingSettlementsTable extends TableComponent
{
    private function getCurrentPropertyId(): ?int
    {
        /** @var User $user */
        $user = Auth::user();
        $partner = $user->partner;

        if (! $partner) {
            return null;
        }

        $countryId = PartnerContext::currentCountryId($partner);

        return $countryId ? PartnerContext::currentPropertyId($partner, $countryId) : null;
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = Auth::user();
        $partner = $user->partner;

        if (! $partner) {
            return '$';
        }

        $countryId = PartnerContext::currentCountryId($partner);

        return ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '$';
    }

    public function getHasPendingSettlements(): bool
    {
        $propertyId = $this->getCurrentPropertyId();

        if (! $propertyId) {
            return false;
        }

        return Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->whereNull('wallet_credited_at')
            ->exists();
    }

    public function table(Table $table): Table
    {
        $propertyId = $this->getCurrentPropertyId();
        $symbol = $this->getCurrencySymbol();

        return $table
            ->query(
                Booking::query()
                    ->when(
                        $propertyId,
                        fn ($q) => $q->where('property_id', $propertyId),
                        fn ($q) => $q->whereRaw('0=1'),
                    )
                    ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
                    ->whereNull('wallet_credited_at')
                    ->orderBy('check_in')
            )
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id')),

                TextColumn::make('booking_number')
                    ->label(__('admin.booked_on'))
                    ->html()
                    ->state(fn (Booking $record): Htmlable => new HtmlString(
                        '<a href="'.e(PartnerBookingView::getUrl(['record' => $record->id])).'" class="font-medium text-primary-600 hover:underline dark:text-primary-400">'.e($record->booking_number).'</a>'
                        .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e($record->created_at->format('d M, Y')).'</p>'
                    )),

                TextColumn::make('guest_name')
                    ->label(__('admin.customer_info'))
                    ->description(fn (Booking $record): ?string => $record->guest_email),

                TextColumn::make('check_in')
                    ->label(__('admin.check_in'))
                    ->date('d M, Y'),

                TextColumn::make('payout')
                    ->label(__('admin.payout_details'))
                    ->state(fn (Booking $record): string => $symbol.number_format(app(CommissionService::class)->calculateCheckInPartnerCredit($record), 2))
                    ->weight(FontWeight::SemiBold),
            ])
            ->emptyStateHeading(__('admin.no_pending_settlements'))
            ->emptyStateDescription('')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50]);
    }
}
