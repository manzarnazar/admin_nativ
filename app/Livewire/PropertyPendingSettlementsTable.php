<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Filament\Pages\BookingView;
use App\Filament\Partner\Pages\PartnerBookingView;
use App\Models\Booking;
use App\Models\Property;
use App\Services\CommissionService;
use Filament\Facades\Filament;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PropertyPendingSettlementsTable extends TableComponent
{
    public int $propertyId;

    public function mount(int $propertyId): void
    {
        $this->propertyId = $propertyId;
    }

    /**
     * Confirmed/checked-in bookings for this property whose check-in has not
     * yet passed the cron's crediting cutoff (wallet_credited_at is still
     * null) — i.e. revenue expected but not yet settled to the wallet.
     * Shared with the parent page's export action for the same reason as
     * PropertyWalletTransactionsTable::buildQuery().
     */
    public static function buildQuery(int $propertyId): Builder
    {
        return Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->whereNull('wallet_credited_at');
    }

    /**
     * @return array<string, string|array{label: string, formatter: callable}>
     */
    public static function exportColumns(): array
    {
        return [
            'id' => __('admin.id'),
            'booking_number' => __('admin.booking_number'),
            'guest_name' => __('admin.customer_info'),
            'guest_email' => __('admin.email_address'),
            'check_in' => __('admin.check_in'),
            'computed_payout' => [
                'label' => __('admin.payout_details'),
                'formatter' => fn (Booking $record): string => number_format(app(CommissionService::class)->calculateCheckInPartnerCredit($record), 2),
            ],
        ];
    }

    public function getHasPendingSettlements(): bool
    {
        return self::buildQuery($this->propertyId)->exists();
    }

    public function table(Table $table): Table
    {
        $currencySymbol = Property::query()->find($this->propertyId)?->country?->currency_symbol ?? '';
        $isAdmin = Filament::getCurrentPanel()?->getId() === 'admin';

        return $table
            ->query(self::buildQuery($this->propertyId)->orderBy('check_in'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id')),

                TextColumn::make('booking_number')
                    ->label(__('admin.booked_on'))
                    ->html()
                    ->state(function (Booking $record) use ($isAdmin): Htmlable {
                        $url = $isAdmin
                            ? BookingView::getUrl(['record' => $record->id])
                            : PartnerBookingView::getUrl(['record' => $record->id]);

                        return new HtmlString(
                            '<a href="'.e($url).'" class="font-medium text-primary-600 hover:underline dark:text-primary-400">#'.e($record->booking_number).'</a>'
                            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e($record->created_at->format('d M, Y')).'</p>'
                        );
                    }),

                TextColumn::make('guest_name')
                    ->label(__('admin.customer_info'))
                    ->description(fn (Booking $record): ?string => $record->guest_email),

                TextColumn::make('check_in')
                    ->label(__('admin.check_in'))
                    ->date('d M, Y'),

                TextColumn::make('payout')
                    ->label(__('admin.payout_details'))
                    ->state(fn (Booking $record): string => $currencySymbol.number_format(app(CommissionService::class)->calculateCheckInPartnerCredit($record), 2))
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Booking $record): string => __('admin.lead_time_days', ['days' => max(0, now()->startOfDay()->diffInDays($record->check_in, false))])),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50]);
    }
}
