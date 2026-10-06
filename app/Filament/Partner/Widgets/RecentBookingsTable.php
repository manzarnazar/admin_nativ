<?php

namespace App\Filament\Partner\Widgets;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Partner\Pages\PartnerBookingView;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Support\DemoMode;
use App\Support\PartnerContext;
use App\Support\UserTimezone;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class RecentBookingsTable extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $partner = $user->partner;

        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;
        $propertyId = ($partner && $countryId) ? PartnerContext::currentPropertyId($partner, $countryId) : null;

        $query = Booking::query()
            ->with([
                // Eager-load with trashed so deleted customers' names/emails still show
                // (mirrors All Bookings; guest_* snapshots are preferred for display).
                'customer' => fn ($q) => $q->withTrashed(),
                'property',
                'propertyRoom.roomType',
            ])
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $currency = $this->getCurrencySymbol($countryId);

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking_number')
                    ->label(__('admin.booking_info'))
                    ->description(function (Booking $record): string {
                        $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->created_at->setTimezone($tz)->format('M d • H:i').' '.UserTimezone::abbreviationFor($tz);
                    })
                    ->color('primary')
                    ->url(fn (Booking $record): string => PartnerBookingView::getUrl(['record' => $record->id])),

                TextColumn::make('customer.name')
                    ->label(__('admin.customer_info'))
                    // Prefer guest_* snapshot — clean original data, untouched by deletion suffix.
                    ->getStateUsing(function (Booking $record): string {
                        $name = $record->guest_name ?: $record->customer?->name ?: 'Guest';
                        if ($record->customer?->trashed()) {
                            $name .= ' ('.__('admin.account_deleted').')';
                        }

                        return $name;
                    })
                    ->description(fn (Booking $record): ?string => DemoMode::maskEmail($record->guest_email ?: $record->customer?->email))
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('propertyRoom.roomType.name')
                    ->label(__('admin.room_details'))
                    ->description(fn (Booking $record): ?string => $record->room_number)
                    ->extraAttributes(['class' => 'room-details-column'])
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('check_in')
                    ->label(__('admin.booking_dates'))
                    ->formatStateUsing(fn (Booking $record): string => $record->check_in->format('M d').' → '.$record->check_out->format('M d'))
                    ->description(fn (Booking $record): string => $record->total_nights.' '.__('admin.night').' / '.($record->total_nights + 1).' '.__('admin.days'))
                    ->wrap(),

                TextColumn::make('total_amount')
                    ->label(__('admin.financial_summary'))
                    ->formatStateUsing(fn (Booking $record) => $currency.number_format((float) $record->total_amount, 2)),

                TextColumn::make('booking_source')
                    ->label(__('admin.booking_source'))
                    ->formatStateUsing(fn (BookingSource $state): string => $state->label()),

                TextColumn::make('payment_status')
                    ->label(__('admin.payment'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->description(fn (Booking $record): ?string => $record->payment_method?->label()),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->color(fn (BookingStatus $state): string => $state->color())
                    ->description(function (Booking $record): ?string {
                        if ($record->status !== BookingStatus::Cancelled || ! $record->cancelled_at) {
                            return null;
                        }
                        $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->cancelled_at->setTimezone($tz)->format('M d • H:i').' '.UserTimezone::abbreviationFor($tz);
                    }),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25]);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    private function getCurrencySymbol(?int $countryId): string
    {
        if (! $countryId) {
            return '₹';
        }

        return Country::query()
            ->where('id', $countryId)
            ->value('currency_symbol') ?? '₹';
    }

    public function render()
    {
        return view('filament.widgets.recent-bookings-table');
    }
}
