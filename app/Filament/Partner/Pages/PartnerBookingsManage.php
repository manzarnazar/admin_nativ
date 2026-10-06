<?php

namespace App\Filament\Partner\Pages;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasSelectRoomAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Mail\CustomerWelcomeMailable;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\RefCountry;
use App\Models\User;
use App\Services\BookingService;
use App\Support\DemoMode;
use App\Support\PartnerContext;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class PartnerBookingsManage extends Page implements HasTable
{
    use HasSelectRoomAction;
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'bookings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.partner.pages.bookings-manage';

    public function getTitle(): string|Htmlable
    {
        return __('admin.all_bookings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.all_bookings');
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
        return __('admin.all_bookings_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    // ── Scoping Helpers ─────────────────────────────────────────────────────

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

    private function getCurrentProperty(): ?Property
    {
        $propertyId = $this->getCurrentPropertyId();

        if (! $propertyId) {
            return null;
        }

        return Property::query()->find($propertyId);
    }

    private function getCurrencySymbol(): string
    {
        $countryId = $this->getCurrentCountryId();

        return ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '$';
    }

    // ── Stats ──────────────────────────────────────────────────────────────

    public function getBookingStats(): array
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();
        $propertyId = $this->getCurrentPropertyId();

        $query = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        return [
            'total' => (clone $query)->whereNotIn('status', [
                BookingStatus::Expired,
                BookingStatus::PendingPayment,
                BookingStatus::Cancelled,
            ])->count(),
            'cancelled' => (clone $query)->where('status', BookingStatus::Cancelled)->count(),
            'from_application' => (clone $query)->where('booking_source', BookingSource::Application)->count(),
            'from_website' => (clone $query)->where('booking_source', BookingSource::Website)->count(),
        ];
    }

    // ── Table ──────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        $partner = $this->getPartner();
        $countryId = $this->getCurrentCountryId();
        $propertyId = $this->getCurrentPropertyId();

        $query = Booking::query()
            ->with([
                // Eager-load with trashed so the partner still sees deleted customers' names/emails
                // (Booking.guest_* snapshots are preferred for display; this is just for the
                // "(account deleted)" indicator).
                'customer' => fn ($q) => $q->withTrashed(),
                'property',
                'propertyRoom.roomType',
                'bookedByAdmin',
                'roomAssignments.room',
            ])
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $currency = $this->getCurrencySymbol();

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_booking_id_or_customer'))
            ->columns([
                TextColumn::make('booking_number')
                    ->label(__('admin.booking_info'))
                    ->description(function (Booking $record): string {
                        $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->created_at->setTimezone($tz)->format('M d • H:i').' '.UserTimezone::abbreviationFor($tz);
                    })
                    ->searchable()
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('guest_name', 'like', "%{$search}%")
                                ->orWhere('guest_email', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'customer',
                                    fn (Builder $cq) => $cq
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                                );
                        });
                    })
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('propertyRoom.roomType.name')
                    ->label(__('admin.room_details'))
                    ->description(function (Booking $record): ?string {
                        $numbers = $record->roomAssignments
                            ->pluck('room.room_number')
                            ->filter()
                            ->join(', ');

                        return filled($numbers) ? $numbers : ($record->room_number ?: null);
                    })
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
                    ->formatStateUsing(fn (Booking $record): string => $currency.number_format((float) $record->total_amount, 2))
                    ->description(function (Booking $record): ?string {
                        if ($record->payment_status === PaymentStatus::Partial) {
                            $property = $record->property;
                            $advance = $property?->advance_percentage ?? 0;

                            return $advance.'% '.__('admin.paid');
                        }

                        return null;
                    })
                    ->toggleable(),

                TextColumn::make('booking_source')
                    ->label(__('admin.booking_source'))
                    ->formatStateUsing(fn (BookingSource $state): string => $state->label())
                    ->toggleable(),

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
                    ->formatStateUsing(function (BookingStatus $state, Booking $record): string {
                        if ($state === BookingStatus::CheckedIn && $record->check_out->isPast()) {
                            return $state->label().' ⚠';
                        }

                        return $state->label();
                    })
                    ->color(function (BookingStatus $state, Booking $record): string {
                        if ($state === BookingStatus::CheckedIn && $record->check_out->isPast()) {
                            return 'danger';
                        }

                        return $state->color();
                    })
                    ->tooltip(function (Booking $record): ?string {
                        if ($record->status === BookingStatus::CheckedIn && $record->check_out->isPast()) {
                            return __('admin.checkout_overdue_warning');
                        }

                        return null;
                    })
                    ->description(function (Booking $record): ?string {
                        if ($record->status !== BookingStatus::Cancelled || ! $record->cancelled_at) {
                            return null;
                        }
                        $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->cancelled_at->setTimezone($tz)->format('M d • H:i').' '.UserTimezone::abbreviationFor($tz);
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect(BookingStatus::cases())
                        ->reject(fn (BookingStatus $s): bool => $s === BookingStatus::Pending)
                        ->mapWithKeys(fn (BookingStatus $s) => [$s->value => $s->label()])
                        ->toArray()),

                SelectFilter::make('booking_source')
                    ->label(__('admin.booking_source'))
                    ->options(collect(BookingSource::cases())->mapWithKeys(fn (BookingSource $s) => [$s->value => $s->label()])->toArray()),

                SelectFilter::make('payment_status')
                    ->label(__('admin.payment'))
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => $s->label()])->toArray()),
            ])
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->visible(fn (Booking $record): bool => ! in_array($record->status, [BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::Completed], true)
                        && (
                            $record->booking_source === BookingSource::Partner
                            || ($record->booking_source !== BookingSource::Partner && $record->property?->pay_at_property)
                            || ($record->status === BookingStatus::CheckedIn && $record->check_out->isPast())
                        ))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalHeading(__('admin.edit_booking'))
                    ->modalWidth('3xl')
                    ->modalSubmitAction(fn ($action) => $action->label(__('admin.save_booking'))->color('primary'))
                    ->modalCancelAction(fn ($action) => $action->label(__('admin.cancel'))->color('gray')->link())
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(function (Booking $record): array {
                        // Only pre-fill cash/upi into the toggle; other methods (pay_online, pay_at_property)
                        // are not selectable in this form and would fail the in: validation.
                        $method = $record->payment_method?->value;
                        $editableMethod = in_array($method, ['cash', 'upi'], true) ? $method : null;

                        return [
                            'room_type_id' => $record->property_room_id,
                            'check_in' => $record->check_in->format('Y-m-d'),
                            'check_out' => $record->check_out->format('Y-m-d'),
                            'booked_rooms' => $record->booked_rooms,
                            'payment_method' => $editableMethod,
                            'transaction_id' => $record->transaction_id,
                            'booking_status' => $record->status->value,
                        ];
                    })
                    ->schema(fn (Booking $record): array => $this->getEditBookingSchema($record))
                    ->action(function (Booking $record, array $data): void {
                        $updates = [];

                        // Only touch payment fields when the form actually exposed them
                        // (i.e. the booking wasn't already paid via an online gateway).
                        if (array_key_exists('payment_method', $data)) {
                            $updates['payment_method'] = ! empty($data['payment_method']) ? $data['payment_method'] : null;
                            $updates['transaction_id'] = $data['transaction_id'] ?? null;
                            $updates['payment_status'] = ! empty($data['payment_method']) ? PaymentStatus::Paid : PaymentStatus::Unpaid;

                            // If booking has a partial online payment and user is now marking as cash/upi,
                            // create a payment record for the remaining amount
                            if (! empty($data['payment_method']) && $record->payment_status === PaymentStatus::Partial) {
                                $successfulPayment = $record->getSuccessfulPayment();
                                if ($successfulPayment && $successfulPayment->payment_type === PaymentType::Partial && $successfulPayment->remaining_amount > 0) {
                                    $paymentMethod = $data['payment_method'] === 'upi' ? PaymentMethod::Upi : PaymentMethod::Cash;
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
                            } elseif (! empty($data['payment_method']) && $record->getSuccessfulPayment() === null) {
                                // Previously-unpaid booking: partner is now recording a manual payment
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
                        }

                        $record->update($updates);

                        $service = app(BookingService::class);
                        $newStatus = $data['booking_status'] ?? null;

                        // Block status progression when payment is not fully settled
                        if (
                            in_array($newStatus, ['confirmed', 'checked_in', 'completed'], true)
                            && in_array($record->fresh()->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Partial], true)
                        ) {
                            Notification::make()->title(__('admin.payment_pending_status_block'))->danger()->send();

                            return;
                        }

                        if ($newStatus === 'confirmed' && $record->status === BookingStatus::Pending) {
                            $service->confirmBooking($record, $data['payment_method'] ?? 'cash', $data['transaction_id'] ?? null);
                            Notification::make()->title(__('admin.booking_confirmed_success'))->success()->send();

                            return;
                        }

                        if ($newStatus === 'checked_in' && $record->status === BookingStatus::Confirmed) {
                            // Room selection is mandatory before check-in.
                            // checkIn() is called only after rooms are assigned in selectRoomsForCheckinAction.
                            $this->loadSelectRoomData($record->id);
                            $this->dispatch('open-checkin-rooms-modal');

                            return;
                        }

                        if ($newStatus === 'completed' && $record->status === BookingStatus::CheckedIn) {
                            $service->checkOut($record);
                            Notification::make()->title(__('admin.check_out_success'))->success()->send();

                            return;
                        }

                        // NOTE: cancellation currently reuses the auto-calculated refund-% logic
                        // (CancellationPolicyService) — no partner-specified override amount and
                        // no wallet/commission split yet. Those land with Phase 6 (commission &
                        // wallet engine). See saas-multimodule-plan.md Section 12.6 / 14 #14.
                        if ($newStatus === 'cancelled' && in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                            $service->cancelBookingWithRefund($record, $data['cancellation_reason'] ?? null, CancellationInitiator::Partner);
                            Notification::make()->title(__('admin.booking_cancelled_success'))->success()->send();

                            return;
                        }

                        if (! empty($data['mark_completed'])) {
                            $service->checkOut($record);
                            Notification::make()->title(__('admin.booking_completed'))->success()->send();

                            return;
                        }

                        Notification::make()->title(__('admin.booking_updated'))->success()->send();
                    }),

                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Booking $record): string => PartnerBookingView::getUrl(['record' => $record->id])),

                Action::make('download')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Booking $record): bool => ! in_array($record->status, [BookingStatus::Expired, BookingStatus::Cancelled], true))
                    ->url(fn (Booking $record): string => route('invoice.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('bookings')
                    ->exports([
                        'booking_number' => 'Booking ID',
                        'customer.name' => 'Customer',
                        'customer.phone' => 'Phone',
                        'propertyRoom.roomType.name' => 'Room Type',
                        'check_in' => ['label' => 'Check-in', 'formatter' => fn (Booking $r): string => $r->check_in->format('M d, Y')],
                        'check_out' => ['label' => 'Check-out', 'formatter' => fn (Booking $r): string => $r->check_out->format('M d, Y')],
                        'total_amount' => ['label' => 'Total Amount', 'formatter' => fn (Booking $r): string => $currency.number_format((float) $r->total_amount, 2)],
                        'booking_source' => ['label' => 'Source', 'formatter' => fn (Booking $r): string => $r->booking_source->label()],
                        'payment_status' => ['label' => 'Payment', 'formatter' => fn (Booking $r): string => $r->payment_status->label()],
                        'status' => ['label' => 'Status', 'formatter' => fn (Booking $r): string => $r->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_bookings_yet'))
            ->emptyStateDescription(__('admin.no_bookings_description'))
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->defaultPaginationPageOption(10);
    }

    // ── Header Actions ─────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            // Add Booking is disabled in multi-mode: partners must never create bookings
            // manually (cash/UPI marked Paid outside a real payment gateway is money the
            // property already holds directly, never counted as platform-collected — see
            // CommissionService::resolveOnlineCollectedAmount()). Multi-mode bookings must
            // only ever be created by the guest through the real online booking flow.
            // Kept here (not deleted) in case a future manual-booking flow is designed
            // with proper wallet-crediting support. ~~~~
            // $this->getAddBookingAction(),
        ];
    }

    private function getAddBookingAction(): Action
    {
        return Action::make('addBooking')
            ->label(__('admin.add_booking'))
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => $this->getCurrentProperty() !== null)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.add_new_booking'))
            ->modalWidth('4xl')
            ->modalSubmitAction(fn ($action) => $action->label(__('admin.save_booking'))->color('primary'))
            ->modalCancelAction(fn ($action) => $action->label(__('admin.cancel'))->color('gray')->link())
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema(fn (): array => $this->getAddBookingSchema())
            ->action(function (array $data): void {
                $service = app(BookingService::class);
                $property = $this->getCurrentProperty();

                /** @var User $partnerUser */
                $partnerUser = auth()->user();

                // Existing customer or create new
                if (! empty($data['customer_id'])) {
                    $customerId = $data['customer_id'];
                } else {
                    if (empty($data['contact_no'] ?? null)) {
                        Notification::make()->title('Contact number is required for new customer.')->danger()->send();

                        return;
                    }

                    if (empty($data['dial_code'] ?? null)) {
                        Notification::make()->title('Dial code is required for new customer.')->danger()->send();

                        return;
                    }

                    ['user' => $customer, 'plain_password' => $plainPassword] = $service->findOrCreateCustomer(
                        $data['contact_no'],
                        $data['new_customer_name'] ?? null,
                        $data['new_customer_email'] ?? null,
                        $data['dial_code'] ?? null,
                    );
                    $customerId = $customer->id;

                    if ($plainPassword !== null && filled($customer->email)) {
                        Mail::to($customer->email)->queue(new CustomerWelcomeMailable($customer, $plainPassword));
                    }
                }

                $service->createBooking($property, [
                    'property_room_id' => $data['room_type_id'],
                    'user_id' => $customerId,
                    'check_in' => $data['check_in'],
                    'check_out' => $data['check_out'],
                    'adults' => $data['adults'] ?? 1,
                    'children' => $data['children'] ?? 0,
                    'booked_rooms' => $data['booked_rooms'] ?? 1,
                    'payment_method' => ! empty($data['payment_method']) ? $data['payment_method'] : null,
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'payment_status' => ! empty($data['payment_method']) ? PaymentStatus::Paid->value : PaymentStatus::Unpaid->value,
                ], $partnerUser);

                Notification::make()->title(__('admin.booking_created'))->success()->send();
            });
    }

    // ── Modal Schemas ──────────────────────────────────────────────────────

    /**
     * @return array<int, Component>
     */
    private function getAddBookingSchema(): array
    {
        $property = $this->getCurrentProperty();
        $countryId = $this->getCurrentCountryId();

        return [
            Grid::make(5)->schema([
                // ── Left column (3/5) ──────────────────────────────────
                Grid::make(1)->schema([
                    // Customer Information
                    Section::make(__('admin.customer_information'))
                        ->icon('heroicon-o-user')
                        ->afterHeader([
                            Action::make('toggleCustomerMode')
                                ->label(fn (Get $get): string => match (true) {
                                    (bool) $get('customer_id') && ! $get('is_new_customer') => __('admin.change_customer'),
                                    (bool) $get('is_new_customer') => __('admin.search_customer'),
                                    default => __('admin.add_customer'),
                                })
                                ->icon(fn (Get $get): string => match (true) {
                                    (bool) $get('customer_id') && ! $get('is_new_customer') => 'heroicon-o-arrow-path',
                                    (bool) $get('is_new_customer') => 'heroicon-o-magnifying-glass',
                                    default => 'heroicon-o-plus',
                                })
                                ->outlined()
                                ->size('sm')
                                ->action(function (Get $get, Set $set): void {
                                    if ($get('customer_id') && ! $get('is_new_customer')) {
                                        $set('customer_id', null);
                                        $set('customer_search', null);
                                    } else {
                                        $set('is_new_customer', ! $get('is_new_customer'));
                                        $set('customer_id', null);
                                        $set('customer_search', null);
                                    }
                                }),
                        ])
                        ->schema([
                            Hidden::make('is_new_customer')
                                ->default(false)
                                ->dehydrated(false)
                                ->live(),

                            // Always carries the selected customer ID
                            Hidden::make('customer_id')
                                ->live(),

                            // Search dropdown (visible only when no customer selected and not new mode)
                            Select::make('customer_search')
                                ->label(__('admin.contact_no'))
                                ->placeholder(__('admin.enter_mobile_number'))
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search): array {
                                    if (strlen($search) < 3) {
                                        return [];
                                    }

                                    return User::query()
                                        ->where('role', 'customer')
                                        ->where(
                                            fn ($q) => $q
                                                ->where('phone', 'like', "%{$search}%")
                                                ->orWhere('name', 'like', "%{$search}%")
                                        )
                                        ->limit(10)
                                        ->get()
                                        ->mapWithKeys(fn (User $user) => [
                                            $user->id => $user->name.' — '.$user->phone,
                                        ])
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name.' — '.User::find($value)?->phone)
                                ->live()
                                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('customer_id', $state))
                                ->dehydrated(false)
                                ->visible(fn (Get $get): bool => ! $get('is_new_customer') && ! $get('customer_id')),

                            // Customer card (shown when customer selected)
                            View::make('filament.schemas.components.booking-customer-card')
                                ->visible(fn (Get $get): bool => ! $get('is_new_customer') && (bool) $get('customer_id')),

                            // New customer fields
                            TextInput::make('new_customer_name')
                                ->label(__('admin.full_name'))
                                ->placeholder('e.g. John Doe')
                                ->required()
                                ->visible(fn (Get $get): bool => (bool) $get('is_new_customer')),

                            Grid::make(1)
                                ->schema([
                                    Grid::make(12)
                                        ->schema([
                                            Select::make('dial_code')
                                                ->label(__('admin.dial_code'))
                                                ->options(RefCountry::query()
                                                    ->where('flag', true)
                                                    ->orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(fn ($c) => [
                                                        "+{$c->phonecode}_{$c->id}" => "{$c->emoji} +{$c->phonecode} <span class='dial-country-name'>{$c->name}</span>",
                                                    ]))
                                                ->allowHtml()
                                                ->searchable()
                                                ->required()
                                                ->default(function () use ($countryId): ?string {
                                                    $phoneCode = $countryId
                                                        ? ltrim((string) (Country::query()->where('id', $countryId)->value('phone_code') ?? ''), '+')
                                                        : '';
                                                    if (! $phoneCode) {
                                                        return null;
                                                    }
                                                    $country = RefCountry::where('phonecode', $phoneCode)->where('flag', true)->orderBy('name')->first();

                                                    return $country ? "+{$country->phonecode}_{$country->id}" : null;
                                                })
                                                ->dehydrateStateUsing(fn (?string $state): ?string => $state
                                                    ? explode('_', $state, 2)[0]
                                                    : null)
                                                ->columnSpan(5),

                                            TextInput::make('contact_no')
                                                ->label(__('admin.contact_number'))
                                                ->placeholder(__('admin.enter_mobile_number'))
                                                ->tel()
                                                ->numeric()
                                                ->required()
                                                ->rules([
                                                    fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get): void {
                                                        $exists = User::query()
                                                            ->where('phone', $value)
                                                            ->where('dial_code', $get('dial_code'))
                                                            ->exists();

                                                        if ($exists) {
                                                            $fail(__('admin.phone_already_exists_search'));
                                                        }
                                                    },
                                                ])
                                                ->columnSpan(7),
                                        ]),

                                    TextInput::make('new_customer_email')
                                        ->label(__('admin.email'))
                                        ->placeholder(__('admin.enter_email'))
                                        ->email(),
                                ])
                                ->visible(fn (Get $get): bool => (bool) $get('is_new_customer')),

                            Text::make(new HtmlString(
                                '<div class="rounded-lg border-l-4 border-amber-400 bg-amber-50 p-3 text-xs text-amber-700 dark:border-amber-600 dark:bg-amber-950/30 dark:text-amber-300">'.
                                    e(__('admin.new_customer_login_credentials_note')).
                                    '</div>'
                            ))
                                ->visible(fn (Get $get): bool => (bool) $get('is_new_customer')),
                        ]),

                    // Stay Details
                    Section::make(__('admin.stay_details'))
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Select::make('room_type_id')
                                ->label(__('admin.room_type'))
                                ->placeholder(__('admin.select_room_type'))
                                ->options(function () use ($property): array {
                                    if (! $property) {
                                        return [];
                                    }

                                    return $property->rooms()
                                        ->with('roomType')
                                        ->get()
                                        ->mapWithKeys(fn (PropertyRoom $room) => [$room->id => $room->roomType->name])
                                        ->toArray();
                                })
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live(),

                            Grid::make(2)->schema([
                                DatePicker::make('check_in')
                                    ->label(__('admin.check_in_date'))
                                    ->placeholder(__('admin.select_check_in_date'))
                                    ->required()
                                    ->live()
                                    ->minDate(today())
                                    ->afterStateUpdated(fn (Set $set) => $set('check_out', null)),

                                DatePicker::make('check_out')
                                    ->label(__('admin.check_out_date'))
                                    ->placeholder(__('admin.select_checkout_date'))
                                    ->required()
                                    ->live()
                                    ->minDate(fn (Get $get) => $get('check_in')
                                        ? Carbon::parse($get('check_in'))->addDay()->format('Y-m-d')
                                        : now()->addDay()->format('Y-m-d'))
                                    ->rules([
                                        fn (Get $get) => function (string $attribute, $value, $fail) use ($get) {
                                            if ($get('check_in') && $value && $value <= $get('check_in')) {
                                                $fail(__('admin.checkout_must_be_after_checkin'));
                                            }
                                        },
                                    ]),
                            ]),

                            TextInput::make('booked_rooms')
                                ->label(__('admin.booked_rooms'))
                                ->placeholder('e.g. 2 Rooms')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live()
                                ->maxValue(function (Get $get) use ($property): ?int {
                                    if (! $property || ! $get('room_type_id')) {
                                        return null;
                                    }

                                    $room = PropertyRoom::query()->find($get('room_type_id'));

                                    if (! $room) {
                                        return null;
                                    }

                                    $checkIn = $get('check_in');
                                    $checkOut = $get('check_out');

                                    if ($checkIn && $checkOut) {
                                        return app(BookingService::class)->getAvailableRooms($room, $checkIn, $checkOut);
                                    }

                                    return $room->total_rooms;
                                })
                                ->validationMessages([
                                    'max' => __('admin.booked_rooms_exceeds_available'),
                                ]),
                        ]),
                ])->columnSpan(3),

                // ── Right column (2/5) ─────────────────────────────────
                Grid::make(1)->schema([
                    // Pricing Summary
                    View::make('filament.schemas.components.booking-pricing-summary'),

                    // Payment Details
                    Section::make(__('admin.payment_details'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            ToggleButtons::make('payment_method')
                                ->hiddenLabel()
                                ->options([
                                    'cash' => __('admin.cash'),
                                    'upi' => __('admin.upi'),
                                ])
                                ->default('cash')
                                ->required()
                                ->grouped()
                                ->live()
                                ->columnSpanFull()
                                ->extraAttributes(['class' => 'payment-toggle']),

                            TextInput::make('transaction_id')
                                ->label(__('admin.transaction_id'))
                                ->placeholder(__('admin.enter_transaction_id'))
                                ->visible(fn (Get $get): bool => $get('payment_method') === 'upi'),
                        ]),
                ])->columnSpan(2),
            ]),
        ];
    }

    private function getEditBookingSchema(Booking $booking): array
    {
        $property = $booking->property;

        return [
            Grid::make(5)->schema([
                // Left column (3/5)
                Grid::make(1)->schema([
                    // Customer card (read-only)
                    Section::make(__('admin.customer_information'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            View::make('filament.schemas.components.booking-customer-card')
                                ->viewData(['forceCustomerId' => $booking->user_id]),
                        ]),

                    // Stay Details (read-only)
                    Section::make(__('admin.stay_details'))
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Select::make('room_type_id')
                                ->label(__('admin.room_type'))
                                ->options(
                                    $property->rooms()
                                        ->with('roomType')
                                        ->get()
                                        ->mapWithKeys(fn (PropertyRoom $room) => [$room->id => $room->roomType->name])
                                        ->toArray()
                                )
                                ->disabled()
                                ->dehydrated(),

                            Grid::make(2)->schema([
                                DatePicker::make('check_in')
                                    ->label(__('admin.check_in_date'))
                                    ->disabled()
                                    ->dehydrated(),

                                DatePicker::make('check_out')
                                    ->label(__('admin.check_out_date'))
                                    ->disabled()
                                    ->dehydrated(),
                            ]),

                            TextInput::make('booked_rooms')
                                ->label(__('admin.booked_rooms'))
                                ->disabled()
                                ->dehydrated(),
                        ]),
                ])->columnSpan(3),

                // Right column (2/5)
                Grid::make(1)->schema([
                    // Pricing Summary (read-only, with payment info for partial payments)
                    View::make('filament.schemas.components.booking-pricing-summary')
                        ->viewData(['booking' => $booking]),

                    // Payment Details (editable only when not already paid)
                    Section::make(__('admin.payment_details'))
                        ->icon('heroicon-o-credit-card')
                        ->visible(fn (): bool => $booking->payment_status !== PaymentStatus::Paid)
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
                ])->columnSpan(2),
            ]),

            // ── Status Change ─────────────────────────────────────────────
            ...($booking->status !== BookingStatus::Cancelled && $booking->status !== BookingStatus::Expired ? [
                Section::make(__('admin.change_booking_status'))
                    ->icon('heroicon-o-arrow-path')
                    ->schema(array_values(array_filter([
                        // Payment pending warning — shown when status progression is blocked
                        TextEntry::make('payment_pending_warn')
                            ->label('')
                            ->state(new HtmlString(
                                '<p class="text-danger-600 text-sm font-medium">⛔ '.__('admin.payment_pending_status_block').'</p>'
                            ))
                            ->visible(fn (Get $get): bool => in_array($booking->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Partial], true) && empty($get('payment_method'))),

                        Radio::make('booking_status')
                            ->label(__('admin.status'))
                            ->options(fn (Get $get): array => match ($booking->status) {
                                BookingStatus::Pending => array_filter([
                                    'pending' => __('admin.pending'),
                                    'confirmed' => ($booking->payment_status === PaymentStatus::Paid || ! empty($get('payment_method'))) ? __('admin.confirmed') : null,
                                    'cancelled' => __('admin.cancelled'),
                                ]),
                                BookingStatus::Confirmed => array_filter([
                                    'confirmed' => __('admin.confirmed'),
                                    'checked_in' => ($booking->payment_status === PaymentStatus::Paid || ! empty($get('payment_method'))) ? __('admin.checked_in_label') : null,
                                    'cancelled' => __('admin.cancelled'),
                                ]),
                                BookingStatus::CheckedIn => array_filter([
                                    'checked_in' => __('admin.checked_in_label'),
                                    'completed' => ($booking->payment_status === PaymentStatus::Paid || ! empty($get('payment_method'))) ? __('admin.completed') : null,
                                ]),
                                default => [],
                            })
                            ->required()
                            ->inline()
                            ->live(),

                        Textarea::make('cancellation_reason')
                            ->label(__('admin.cancellation_reason'))
                            ->placeholder(__('admin.cancellation_reason_placeholder'))
                            ->rows(3)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('booking_status') === 'cancelled'),

                        // Early check-in warning
                        ($booking->status === BookingStatus::Confirmed && now()->startOfDay()->lt($booking->check_in->startOfDay()))
                            ? TextEntry::make('early_checkin_warn')
                                ->label('')
                                ->state(new HtmlString(
                                    '<p class="text-warning-600 text-sm font-medium">⚠ '.__('admin.early_checkin_warning', ['days' => now()->startOfDay()->diffInDays($booking->check_in->startOfDay())]).'</p>'
                                ))
                                ->visible(fn (Get $get): bool => $get('booking_status') === 'checked_in')
                            : null,

                        // Early check-out warning
                        ($booking->status === BookingStatus::CheckedIn && now()->startOfDay()->lt($booking->check_out->startOfDay()))
                            ? TextEntry::make('early_checkout_warn')
                                ->label('')
                                ->state(new HtmlString(
                                    '<p class="text-warning-600 text-sm font-medium">⚠ '.__('admin.early_checkout_warning', ['days' => now()->startOfDay()->diffInDays($booking->check_out->startOfDay())]).'</p>'
                                ))
                                ->visible(fn (Get $get): bool => $get('booking_status') === 'completed')
                            : null,

                        // Late check-out warning
                        ($booking->status === BookingStatus::CheckedIn && now()->startOfDay()->gt($booking->check_out->startOfDay()))
                            ? TextEntry::make('late_checkout_warn')
                                ->label('')
                                ->state(new HtmlString(
                                    '<div class="rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-950/30">'
                                        .'<p class="text-sm font-medium text-orange-800 dark:text-orange-200">⚠ '.__('admin.late_checkout_warning', ['days' => $booking->check_out->startOfDay()->diffInDays(now()->startOfDay())]).'</p>'
                                        .'<p class="mt-1 text-xs text-orange-600 dark:text-orange-400">'.__('admin.late_checkout_new_booking_suggestion').'</p>'
                                        .'</div>'
                                ))
                                ->visible(fn (Get $get): bool => $get('booking_status') === 'completed')
                            : null,
                    ]))),
            ] : []),
        ];
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
