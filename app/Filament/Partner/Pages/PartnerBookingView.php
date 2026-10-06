<?php

namespace App\Filament\Partner\Pages;

use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Partner;
use App\Models\User;
use App\Services\BookingService;
use App\Support\PartnerContext;
use App\Support\UserTimezone;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class PartnerBookingView extends Page implements DeclaresTopbarControls
{
    use RequiresApprovedPartner;

    protected static ?string $slug = 'bookings/{record}/view';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.partner.pages.booking-view';

    public ?int $bookingId = null;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        if (! $this->bookingId) {
            return '';
        }

        return $this->getBooking()->booking_number;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::Bookings;
    }

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    public function mount(int $record): void
    {
        $booking = Booking::query()->with('property')->findOrFail($record);
        $partner = $this->getPartner();

        // Ownership check, not just a country match — a partner must never be able to
        // view another partner's booking by guessing IDs within the same country.
        if (! $partner || $booking->property?->partner_id !== $partner->id) {
            Notification::make()
                ->title(__('admin.partner_booking_access_denied'))
                ->warning()
                ->send();

            $this->redirect(PartnerBookingsManage::getUrl());

            return;
        }

        $this->bookingId = $booking->id;
    }

    public function getBooking(): Booking
    {
        if (! $this->bookingId) {
            $this->redirect(PartnerBookingsManage::getUrl());

            // Return a dummy instance so the blade doesn't crash while redirect is queued.
            return new Booking;
        }

        return Booking::query()
            ->with([
                // Eager-load the customer even if soft-deleted so the partner can still see who
                // booked (after a customer deletes their account). Booking.guest_* snapshots are
                // preferred for display; the user record is just used to detect "deleted" state.
                'customer' => fn ($q) => $q->withTrashed(),
                'property',
                'propertyRoom.roomType',
                'bookedByAdmin',
                'payments.refunds',
                'promoCode',
                'coupon',
                'roomAssignments.room',
            ])
            ->findOrFail($this->bookingId);
    }

    public function getCurrencySymbol(): string
    {
        $partner = $this->getPartner();
        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;

        return ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '$';
    }

    public function getTimezone(): string
    {
        return $this->getBooking()->property?->resolvedTimezone() ?? UserTimezone::current();
    }

    /**
     * No partner-facing customer profile page exists yet, so this always renders as
     * plain text rather than a link (see project_partner_bookings_phase7 memory).
     */
    public function getCustomerViewUrl(): ?string
    {
        return null;
    }

    /**
     * No partner-facing payment detail page exists yet, so this always renders as
     * plain text rather than a link (see project_partner_bookings_phase7 memory).
     */
    public function getPaymentViewUrl(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelBookingAction(),
        ];
    }

    private function getCancelBookingAction(): Action
    {
        return Action::make('cancelBooking')
            ->label(__('admin.cancel_booking'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (): bool => in_array($this->getBooking()->status, [BookingStatus::Pending, BookingStatus::PendingPayment, BookingStatus::Confirmed], true))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.cancel_booking'))
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.confirm_cancellation'))
            ->modalCancelActionLabel(__('admin.close'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->schema(fn (): array => $this->getCancelBookingSchema())
            ->action(function (array $data): void {
                $booking = $this->getBooking();
                app(BookingService::class)->cancelBookingWithRefund(
                    $booking,
                    $data['cancellation_reason'],
                    CancellationInitiator::Partner,
                );
                Notification::make()->title(__('admin.booking_cancelled_success'))->success()->send();
            });
    }

    private function getCancelBookingSchema(): array
    {
        $booking = $this->getBooking();
        $currency = $this->getCurrencySymbol();

        // Partner cancellations always give the customer a 100% refund.
        $payment = $booking->getSuccessfulPayment();
        $refundAmount = $payment ? (float) $payment->amount : 0;
        $isManualPayment = $booking->payment_method && in_array($booking->payment_method, [PaymentMethod::Cash, PaymentMethod::Upi, PaymentMethod::PayAtProperty], true);

        $refundContent = $refundAmount > 0
            ? new HtmlString(
                '<span class="text-success-600 font-semibold">'
                    .$currency.number_format($refundAmount, 2)
                    .' (100%)</span>'
            )
            : new HtmlString(
                '<span class="text-gray-500 font-semibold">'.__('admin.no_payment_to_refund').'</span>'
            );

        $schema = [
            Section::make(__('admin.booking_details'))
                ->schema([
                    TextEntry::make('booking_number_display')
                        ->label(__('admin.booking_number'))
                        ->state($booking->booking_number),

                    TextEntry::make('dates_display')
                        ->label(__('admin.booking_dates'))
                        ->state($booking->check_in->format('M d, Y').' → '.$booking->check_out->format('M d, Y')),

                    TextEntry::make('amount_display')
                        ->label(__('admin.total_amount'))
                        ->state($currency.number_format((float) $booking->total_amount, 2)),
                ])
                ->columns(3),

            Section::make(__('admin.refund_preview'))
                ->schema(array_values(array_filter([
                    TextEntry::make('refund_amount_display')
                        ->label(__('admin.refund_amount'))
                        ->state($refundContent),

                    $payment && $refundAmount > 0 && $isManualPayment
                        ? TextEntry::make('manual_refund_note')
                            ->label('')
                            ->state(new HtmlString(
                                '<p class="text-warning-600 text-sm">'.__('admin.manual_refund_note').'</p>'
                            ))
                        : null,
                ]))),

            Section::make(__('admin.cancellation_reason_section'))
                ->schema([
                    Textarea::make('cancellation_reason')
                        ->label(__('admin.cancellation_reason'))
                        ->placeholder(__('admin.cancellation_reason_placeholder'))
                        ->required()
                        ->minLength(5)
                        ->maxLength(500)
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];

        return array_values(array_filter($schema));
    }
}
