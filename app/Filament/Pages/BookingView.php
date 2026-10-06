<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Filament\Concerns\ResolvesBackUrl;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CancellationPolicyService;
use App\Services\Payments\PaymentService;
use App\Support\SystemMode;
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

class BookingView extends Page implements DeclaresTopbarControls
{
    use ResolvesBackUrl;

    protected static ?string $slug = 'bookings/{record}/view';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.booking-view';

    public ?int $bookingId = null;

    public ?string $backUrl = null;

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
        return NavigationGroup::resolve(NavigationGroup::Bookings);
    }

    public function mount(int $record): void
    {
        $booking = Booking::query()->with('property')->findOrFail($record);

        /** @var User $user */
        $user = auth()->user();

        if ($booking->property->country_id !== $user->current_country_id) {
            Notification::make()
                ->title(__('admin.booking_not_in_country'))
                ->warning()
                ->send();

            $this->redirect(AllBookingsManage::getUrl());

            return;
        }

        $this->bookingId = $booking->id;
        $this->backUrl = $this->resolveBackUrl(AllBookingsManage::getUrl());
    }

    public function getBooking(): Booking
    {
        if (! $this->bookingId) {
            $this->redirect(AllBookingsManage::getUrl());

            // Return a dummy instance so the blade doesn't crash while redirect is queued.
            return new Booking;
        }

        return Booking::query()
            ->with([
                // Eager-load the customer even if soft-deleted so admin can still see who booked
                // (after a customer deletes their account). Booking.guest_* snapshots are preferred
                // for display; the user record is just used to detect "deleted" state + link to profile.
                'customer' => fn ($q) => $q->withTrashed(),
                'property.refCity',
                'property.refState',
                'property.country',
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
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    public function getTimezone(): string
    {
        return $this->getBooking()->property?->resolvedTimezone() ?? UserTimezone::current();
    }

    public function getCustomerViewUrl(): ?string
    {
        $customer = $this->getBooking()->customer;

        if (! $customer) {
            return null;
        }

        return CustomerView::getUrl(['record' => $customer->id]);
    }

    public function getPaymentViewUrl(): ?string
    {
        $payment = $this->getBooking()->getSuccessfulPayment();

        if (! $payment) {
            return null;
        }

        return ViewPayment::getUrl(['record' => $payment->id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getMarkRefundCompleteAction(),
            $this->getCancelBookingAction(),
        ];
    }

    private function getMarkRefundCompleteAction(): Action
    {
        return Action::make('markRefundComplete')
            ->label(__('admin.mark_refund_as_complete'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(function (): bool {
                // Currently restricted to multi-mode only.
                // To enable for single-mode as well, remove the SystemMode::isMulti() guard:
                //   return $pendingRefund !== null;
                if (! SystemMode::isMulti()) {
                    return false;
                }

                $booking = $this->getBooking();
                $pendingRefund = $booking->payments
                    ->flatMap(fn ($p) => $p->refunds)
                    ->first(fn ($r) => $r->status === RefundStatus::Pending);

                return $pendingRefund !== null;
            })
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.mark_refund_as_complete'))
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.mark_refund_as_complete'))
            ->modalCancelActionLabel(__('admin.close'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->schema(function (): array {
                $booking = $this->getBooking();
                $currency = $this->getCurrencySymbol();
                $refund = $booking->payments
                    ->flatMap(fn ($p) => $p->refunds)
                    ->first(fn ($r) => $r->status === RefundStatus::Pending);
                $isGatewayPayment = ! in_array($booking->payment_method, [PaymentMethod::Cash, PaymentMethod::Upi, PaymentMethod::PayAtProperty], true);

                $schema = [
                    Section::make(__('admin.booking_details'))
                        ->schema([
                            TextEntry::make('booking_number_display')
                                ->label(__('admin.booking_number'))
                                ->state($booking->booking_number),

                            TextEntry::make('refund_amount_display')
                                ->label(__('admin.refund_amount'))
                                ->state(new HtmlString(
                                    '<span class="text-success-600 font-semibold">'
                                        .$currency.number_format((float) ($refund?->amount ?? 0), 2)
                                        .'</span>'
                                )),
                        ])
                        ->columns(2),
                ];

                if ($isGatewayPayment) {
                    $schema[] = Section::make()
                        ->schema([
                            TextEntry::make('gateway_warning')
                                ->label('')
                                ->state(new HtmlString(
                                    '<p class="text-warning-600 text-sm font-medium">'
                                        .__('admin.gateway_refund_warning')
                                        .'</p>'
                                )),
                        ]);
                }

                $schema[] = Section::make()
                    ->schema([
                        TextEntry::make('confirm_note')
                            ->label('')
                            ->state(new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-400">'
                                    .__('admin.mark_refund_complete_confirm', [
                                        'amount' => $currency.number_format((float) ($refund?->amount ?? 0), 2),
                                    ])
                                    .'</p>'
                            )),
                    ]);

                return $schema;
            })
            ->action(function (): void {
                $booking = $this->getBooking();
                $refund = $booking->payments
                    ->flatMap(fn ($p) => $p->refunds)
                    ->first(fn ($r) => $r->status === RefundStatus::Pending);

                if (! $refund) {
                    return;
                }

                app(PaymentService::class)->markManualRefundComplete($refund);

                Notification::make()
                    ->title(__('admin.refund_marked_complete'))
                    ->success()
                    ->send();
            });
    }

    private function getCancelBookingAction(): Action
    {
        $booking = $this->getBooking();

        return Action::make('cancelBooking')
            ->label(__('admin.cancel_booking'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            // In multi-SAAS mode admin is a platform overseer, not an operator.
            // Cancellations should go through the customer/partner flow, not admin directly.
            ->visible(fn (): bool => SystemMode::isSingle()
                && in_array($this->getBooking()->status, [BookingStatus::Pending, BookingStatus::PendingPayment, BookingStatus::Confirmed], true))
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
                app(BookingService::class)->cancelBookingWithRefund($booking, $data['cancellation_reason'], CancellationInitiator::Admin);
                Notification::make()->title(__('admin.booking_cancelled_success'))->success()->send();
            });
    }

    private function getCancelBookingSchema(): array
    {
        $booking = $this->getBooking();

        /** @var User $user */
        $user = auth()->user();

        $currency = Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';

        $refundPercentage = app(CancellationPolicyService::class)->calculateRefundPercentage($booking);
        $payment = $booking->getSuccessfulPayment();
        $refundAmount = app(CancellationPolicyService::class)->calculateRefundAmount($booking, $refundPercentage);
        $isManualPayment = $booking->payment_method && in_array($booking->payment_method, [PaymentMethod::Cash, PaymentMethod::Upi, PaymentMethod::PayAtProperty], true);

        $refundContent = $refundAmount > 0
            ? new HtmlString(
                '<span class="text-success-600 font-semibold">'
                    .$currency.number_format((float) $refundAmount, 2)
                    .' ('.$refundPercentage.'%)</span>'
            )
            : new HtmlString(
                '<span class="text-danger-600 font-semibold">'.__('admin.no_refund_applicable').'</span>'
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
