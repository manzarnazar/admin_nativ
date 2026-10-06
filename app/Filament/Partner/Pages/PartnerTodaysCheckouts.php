<?php

namespace App\Filament\Partner\Pages;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Partner;
use App\Models\User;
use App\Services\BookingService;
use App\Support\DemoMode;
use App\Support\PartnerContext;
use App\Support\UserTimezone;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class PartnerTodaysCheckouts extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'todays-checkouts';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.partner.pages.todays-checkouts';

    public function getTitle(): string|Htmlable
    {
        return __('admin.todays_checkouts');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.todays_checkouts');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::Bookings;
    }

    public function getSubheading(): ?string
    {
        return __('admin.todays_checkouts_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    private function getCurrentCountryId(): ?int
    {
        $partner = $this->getPartner();

        return $partner ? PartnerContext::currentCountryId($partner) : null;
    }

    private function getCurrentPropertyId(): ?int
    {
        $countryId = $this->getCurrentCountryId();
        $partner = $this->getPartner();

        return ($partner && $countryId) ? PartnerContext::currentPropertyId($partner, $countryId) : null;
    }

    public function hasCheckouts(): bool
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();
        $propertyId = $this->getCurrentPropertyId();

        $query = Booking::query()
            ->whereDate('check_out', today())
            ->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::Completed])
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        return $query->exists();
    }

    private function getCurrencySymbol(): string
    {
        $countryId = $this->getCurrentCountryId();

        return ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '$';
    }

    public function table(Table $table): Table
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();
        $propertyId = $this->getCurrentPropertyId();

        $query = Booking::query()
            ->with(['customer', 'property', 'propertyRoom.roomType'])
            ->whereDate('check_out', today())
            ->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::Completed])
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $currency = $this->getCurrencySymbol();

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_property_name_or_id'))
            ->columns([
                TextColumn::make('booking_number')
                    ->label(__('admin.booking_info'))
                    ->description(function (Booking $record): string {
                        $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->created_at->setTimezone($tz)->format('M d • H:i').' '.UserTimezone::abbreviationFor($tz);
                    })
                    ->searchable()
                    ->color('primary'),

                TextColumn::make('customer.name')
                    ->label(__('admin.customer_info'))
                    ->description(fn (Booking $record): ?string => DemoMode::maskEmail($record->customer?->email))
                    ->searchable()
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('property.name')
                    ->searchable()
                    ->hidden(),

                TextColumn::make('propertyRoom.roomType.name')
                    ->label(__('admin.room_details'))
                    ->description(fn (Booking $record): ?string => $record->room_number)
                    ->extraAttributes(['class' => 'room-details-column'])
                    ->limit(20)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('check_in')
                    ->label(__('admin.booking_dates'))
                    ->formatStateUsing(fn (Booking $record): string => $record->check_in->format('M d').' → '.$record->check_out->format('M d'))
                    ->description(fn (Booking $record): string => $record->total_nights.' '.__('admin.night').' / '.($record->total_nights + 1).' '.__('admin.days'))
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label(__('admin.financial_summary'))
                    ->formatStateUsing(fn (Booking $record): string => $currency.number_format((float) $record->total_amount, 2))
                    ->toggleable(),

                TextColumn::make('booking_source')
                    ->label(__('admin.booking_source'))
                    ->formatStateUsing(fn (BookingSource $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_status')
                    ->label(__('admin.payment'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->description(fn (Booking $record): ?string => $record->payment_method?->label())
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->color(fn (BookingStatus $state): string => $state->color())
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'checked_in' => 'Checked-In',
                        'completed' => 'Completed',
                    ]),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('todays-check-outs')
                    ->exports([
                        'booking_number' => 'Booking ID',
                        'customer.name' => 'Customer Name',
                        'property.name' => 'Property',
                        'propertyRoom.roomType.name' => 'Room Type',
                        'room_number' => 'Room Number',
                        'check_in' => ['label' => 'Check-In', 'formatter' => fn (Booking $record): string => $record->check_in->format('d M Y')],
                        'check_out' => ['label' => 'Check-Out', 'formatter' => fn (Booking $record): string => $record->check_out->format('d M Y')],
                        'total_amount' => 'Total Amount',
                        'payment_status' => ['label' => 'Payment Status', 'formatter' => fn (Booking $record): string => $record->payment_status->label()],
                        'status' => ['label' => 'Status', 'formatter' => fn (Booking $record): string => $record->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->visible(fn (Booking $record): bool => $record->status === BookingStatus::CheckedIn)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalHeading(__('admin.booking_details'))
                    ->modalWidth('lg')
                    ->modalSubmitActionLabel(__('admin.save_booking'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (Booking $record): array => [
                        'checkout_status' => $record->status->value,
                    ])
                    ->schema(fn (Booking $record): array => [
                        View::make('filament.schemas.components.booking-details-readonly')
                            ->viewData(['booking' => $record]),

                        Section::make()
                            ->schema([
                                Radio::make('checkout_status')
                                    ->label(__('admin.status'))
                                    ->options([
                                        'checked_in' => __('admin.checked_in_label'),
                                        'completed' => __('admin.completed_label'),
                                    ])
                                    ->required()
                                    ->inline(),
                            ]),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        if ($data['checkout_status'] === 'completed' && $record->status === BookingStatus::CheckedIn) {
                            app(BookingService::class)->checkOut($record);
                        }

                        Notification::make()->title(__('admin.booking_updated'))->success()->send();
                    }),

                Action::make('download')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Booking $record): string => route('invoice.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultPaginationPageOption(5);
    }
}
