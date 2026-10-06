<?php

namespace App\Filament\Partner\Pages;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasSelectRoomAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use App\Support\DemoMode;
use App\Support\PartnerContext;
use App\Support\UserTimezone;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class PartnerTodaysCheckins extends Page implements HasTable
{
    use HasSelectRoomAction;
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'todays-check-ins';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.partner.pages.todays-checkins';

    public function getTitle(): string|Htmlable
    {
        return __('admin.todays_checkins');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.todays_checkins');
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
        return __('admin.todays_checkins_subheading');
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

    public function hasCheckins(): bool
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();
        $propertyId = $this->getCurrentPropertyId();

        $query = Booking::query()
            ->whereDate('check_in', today())
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
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
            ->whereDate('check_in', today())
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
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
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked-In',
                    ]),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('todays-check-ins')
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
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->extraModalWindowAttributes(['class' => 'todays-checkins-edit-modal'])
                    ->modalHeading(__('admin.booking_details'))
                    ->modalWidth('lg')
                    ->modalSubmitActionLabel(__('admin.save_booking'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (Booking $record): array => [
                        'checkin_status' => $record->status->value,
                        'room_number' => $record->room_number,
                        'payment_method' => in_array($record->payment_method?->value, ['cash', 'upi'], true) ? $record->payment_method->value : null,
                        'transaction_id' => $record->transaction_id,
                    ])
                    ->schema(fn (Booking $record): array => [
                        View::make('filament.schemas.components.booking-details-readonly')
                            ->viewData([
                                'booking' => $record,
                                'hide_payment_card' => $record->payment_status !== PaymentStatus::Paid,
                            ]),

                        Section::make(__('admin.payment_details'))
                            ->icon('heroicon-o-credit-card')
                            ->visible(fn (): bool => $record->payment_status !== PaymentStatus::Paid)
                            ->schema([
                                ToggleButtons::make('payment_method')
                                    ->hiddenLabel()
                                    ->options([
                                        'cash' => __('admin.cash'),
                                        'upi' => __('admin.upi'),
                                    ])
                                    ->grouped()
                                    ->live()
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'payment-toggle']),

                                TextInput::make('transaction_id')
                                    ->label(__('admin.transaction_id'))
                                    ->placeholder(__('admin.enter_transaction_id'))
                                    ->visible(fn (Get $get): bool => $get('payment_method') === 'upi'),
                            ]),

                        Section::make()
                            ->schema([
                                Radio::make('checkin_status')
                                    ->label(__('admin.status'))
                                    ->options([
                                        'confirmed' => __('admin.confirmed'),
                                        'checked_in' => __('admin.checked_in_label'),
                                    ])
                                    ->required()
                                    ->inline(),
                            ]),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        if (! empty($data['payment_method']) && $record->payment_status !== PaymentStatus::Paid) {
                            $paymentMethod = $data['payment_method'] === 'upi' ? PaymentMethod::Upi : PaymentMethod::Cash;

                            if ($record->payment_status === PaymentStatus::Partial) {
                                $successfulPayment = $record->getSuccessfulPayment();
                                if ($successfulPayment && $successfulPayment->payment_type === PaymentType::Partial && $successfulPayment->remaining_amount > 0) {
                                    Payment::create([
                                        'booking_id' => $record->id,
                                        'user_id' => $record->user_id,
                                        'amount' => $successfulPayment->remaining_amount,
                                        'currency' => $successfulPayment->currency,
                                        'payment_type' => PaymentType::Full,
                                        'status' => PaymentTransactionStatus::Success,
                                        'payment_method' => $paymentMethod,
                                        'gateway_type' => PaymentGateway::Manual,
                                        'paid_at' => now(),
                                        'processed_at' => now(),
                                        'metadata' => [
                                            'payment_method' => $data['payment_method'],
                                            'transaction_id' => $data['transaction_id'] ?? null,
                                        ],
                                        'gateway_response' => [
                                            'collected_at_property' => true,
                                            'collected_by' => auth()->id(),
                                            'payment_method' => $data['payment_method'],
                                            'transaction_id' => $data['transaction_id'] ?? null,
                                        ],
                                    ]);
                                }
                            } else {
                                Payment::create([
                                    'booking_id' => $record->id,
                                    'user_id' => $record->user_id,
                                    'amount' => $record->total_amount,
                                    'currency' => $record->currency_code ?? 'INR',
                                    'payment_type' => PaymentType::Full,
                                    'status' => PaymentTransactionStatus::Success,
                                    'gateway_type' => PaymentGateway::Manual,
                                    'paid_at' => now(),
                                    'processed_at' => now(),
                                    'metadata' => [
                                        'payment_method' => $data['payment_method'],
                                        'transaction_id' => $data['transaction_id'] ?? null,
                                        'confirmed_by' => auth()->id(),
                                    ],
                                    'gateway_response' => [
                                        'collected_at_property' => true,
                                        'collected_by' => auth()->id(),
                                        'payment_method' => $data['payment_method'],
                                        'transaction_id' => $data['transaction_id'] ?? null,
                                    ],
                                ]);
                            }

                            $record->update([
                                'payment_method' => $paymentMethod,
                                'transaction_id' => $data['transaction_id'] ?? null,
                                'payment_status' => PaymentStatus::Paid,
                            ]);
                        }

                        // If checking in, open the room selection modal first.
                        // checkIn() is only called after rooms are assigned in selectRoomsForCheckinAction.
                        if ($data['checkin_status'] === 'checked_in' && $record->status === BookingStatus::Confirmed) {
                            // Same guard as PartnerBookingsManage.php's Edit action — evaluated
                            // after the payment-recording block above, so filling in payment
                            // details in this same submission already satisfies it (payment_status
                            // is Paid by this point, no need to re-fetch).
                            if (in_array($record->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Partial], true)) {
                                Notification::make()->title(__('admin.payment_pending_status_block'))->danger()->send();

                                return;
                            }

                            $this->loadSelectRoomData($record->id);
                            $this->dispatch('open-checkin-rooms-modal');

                            return;
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

    #[On('open-checkin-rooms-modal')]
    public function openCheckinRoomsModal(): void
    {
        $this->mountAction('selectRoomsForCheckin');
    }

    /**
     * Room selection modal opened automatically when the partner checks in a booking.
     * checkIn() is only called here — after rooms are validated and persisted.
     */
    public function selectRoomsForCheckinAction(): Action
    {
        return Action::make('selectRoomsForCheckin')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.select_rooms'))
            ->modalWidth('5xl')
            ->modalSubmitActionLabel(__('admin.confirm_check_in'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->extraModalWindowAttributes(['class' => 'swap-modal-buttons'])
            ->modalFooterActionsAlignment(Alignment::Start)
            ->schema(function (): array {
                $booking = Booking::with('propertyRoom.roomType')->find($this->selectingBookingId);

                return [
                    View::make('filament.schemas.components.select-room-grid')
                        ->viewData([
                            'booked_rooms' => $booking?->booked_rooms ?? 0,
                            'room_type_name' => $booking?->propertyRoom?->roomType?->name ?? '',
                            'check_in' => $booking?->check_in->format('M d, Y') ?? '',
                            'check_out' => $booking?->check_out->format('M d, Y') ?? '',
                        ]),
                ];
            })
            ->action(function (): void {
                $booking = Booking::with(['propertyRoom', 'roomAssignments'])->find($this->selectingBookingId);

                if (! $booking) {
                    return;
                }

                $this->validatePendingRooms(
                    required: $booking->booked_rooms,
                    propertyRoomId: $booking->property_room_id,
                    checkIn: $booking->check_in->toDateString(),
                    checkOut: $booking->check_out->toDateString(),
                    excludeBookingId: $booking->id,
                );

                try {
                    DB::transaction(function () use ($booking): void {
                        $this->persistRoomAssignments($booking->id);
                        app(BookingService::class)->checkIn($booking);
                    });

                    Notification::make()->title(__('admin.check_in_success'))->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title(__('admin.'.$e->getMessage()))->danger()->send();
                }
            });
    }
}
