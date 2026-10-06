<x-filament-panels::page>
    <style>
        @media (max-width: 767px) {
            /* Header: stack booking number/status above download button */
            .bv-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }
            .bv-header-left {
                flex-wrap: wrap !important;
            }
            .bv-header-download {
                width: 100% !important;
            }
            .bv-header-download a,
            .bv-header-download > a > * {
                width: 100% !important;
                justify-content: center !important;
            }
            /* Main content: single column instead of 3-col grid */
            .bv-content-grid {
                grid-template-columns: 1fr !important;
            }
            /* Left col: full width */
            .bv-left-col {
                grid-column: span 1 !important;
            }
            /* Payment cards: stack vertically */
            .bv-payment-cards {
                grid-template-columns: 1fr !important;
            }
            /* Booking details grid: 2 columns instead of 3 */
            .bv-booking-details-grid {
                grid-template-columns: 1fr 1fr !important;
            }
            /* Contact info: stack phone + email vertically */
            .bv-contact-row {
                flex-direction: column !important;
                gap: 16px !important;
            }
            /* Cancellation details: single column */
            .bv-cancellation-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    @php
    $booking = $this->getBooking();
    $customer = $booking->customer;
    $customerDeleted = $customer && method_exists($customer, 'trashed') && $customer->trashed();
    // Prefer the booking's guest_* snapshots — they're the original data captured at booking
    // time, clean even if the customer later changed details or deleted the account.
    $displayName = $booking->guest_name ?: $customer?->name ?: 'Guest';
    $displayEmail = \App\Support\DemoMode::maskEmail($booking->guest_email ?: $customer?->email);
    $rawPhone = $booking->guest_phone
        ? (($booking->guest_dial_code ? $booking->guest_dial_code.' ' : '').$booking->guest_phone)
        : ($customer?->phone ? (($customer->dial_code ? $customer->dial_code.' ' : '').$customer->phone) : null);
    $rawDialCode = $booking->guest_phone
        ? ($booking->guest_dial_code ?? '')
        : ($customer?->dial_code ?? '');
    $rawPhoneOnly = $booking->guest_phone ?: $customer?->phone;
    $displayPhone = $rawPhoneOnly
        ? ($rawDialCode ? $rawDialCode.' ' : '').\App\Support\DemoMode::maskPhone($rawPhoneOnly)
        : null;
    $roomType = $booking->propertyRoom?->roomType;
    $currency = $this->getCurrencySymbol();
    $timezone = $this->getTimezone();
    $tzAbbr = \Carbon\Carbon::now($timezone)->format('T');
    $refunds = $booking->payments->flatMap->refunds;
    $isLateCheckout = $booking->status === \App\Enums\BookingStatus::Completed
    && $booking->actual_checkout_at
    && $booking->actual_checkout_at->startOfDay()->gt($booking->check_out->startOfDay());
    $successfulPayments = $booking->payments->filter(fn ($p) => $p->status === \App\Enums\PaymentTransactionStatus::Success);
    $totalPaid = (float) $successfulPayments->sum('amount');
    $transactionId = $successfulPayments->first()?->gateway_payment_id ?? $booking->transaction_id;
    $onlineCollected = app(\App\Services\CommissionService::class)->resolveOnlineCollectedAmount($booking);
    $manualCollected = max(0.0, $totalPaid - $onlineCollected);
    // Cash collected at the property still includes its own tax slice — strip that out the
    // same way calculateCheckInPartnerCredit() already does for the online portion, so
    // "Partner Earning" always means the same thing (pure room revenue, tax-free) regardless
    // of which channel the money came through.
    $manualRoomPortion = (float) $booking->total_amount > 0
        ? round($manualCollected * ((float) $booking->base_amount / (float) $booking->total_amount), 2)
        : 0.0;
    @endphp

    {{-- Back Link --}}
    <div class="-mt-4 mb-3">
        <a
            href="{{ \App\Filament\Partner\Pages\PartnerBookingsManage::getUrl() }}"
            wire:navigate
            class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_all_bookings') }}
        </a>
    </div>

    {{-- Header --}}
    <div class="bv-header flex items-center justify-between">
        <div class="bv-header-left flex items-center gap-3">
            <h1 class="text-xl font-bold text-gray-950 dark:text-white">{{ $booking->booking_number }}</h1>
            <span @class([ 'inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium' , 'bg-blue-100 text-blue-700'=> $booking->status->value === 'confirmed',
                'bg-green-100 text-green-700' => $booking->status->value === 'checked_in',
                'bg-gray-100 text-gray-700' => $booking->status->value === 'completed',
                'bg-red-100 text-red-700' => $booking->status->value === 'cancelled',
                'bg-yellow-100 text-yellow-700' => $booking->status->value === 'pending',
                ])>
                {{ $booking->status->label() }}
            </span>
            @if ($isLateCheckout)
            <span class="inline-flex items-center gap-1 rounded-md bg-orange-100 px-2.5 py-1 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                <x-heroicon-o-clock class="h-3.5 w-3.5" />
                {{ __('admin.late_checkout') }} · {{ $booking->actual_checkout_at->setTimezone($timezone)->format('M d, H:i') }} {{ $tzAbbr }}
            </span>
            @endif
        </div>

        <div class="bv-header-download">
            <a href="{{ route('invoice.download', $booking) }}" target="_blank">
                <x-filament::button color="primary" icon="heroicon-o-arrow-down-tray">
                    {{ __('admin.download_invoice') }}
                </x-filament::button>
            </a>
        </div>
    </div>

    {{-- Content --}}
    <div class="bv-content-grid section-bg-gray grid grid-cols-3 gap-7 -mx-8 -mb-8 px-8 pt-6 pb-8">

        {{-- Left Column (2/3) --}}
        <div class="bv-left-col col-span-2 space-y-7">

            {{-- Customer Details --}}
            <div class="rounded-2xl border border-[#EDEDED] overflow-hidden dark:border-gray-700">
                <div class="p-6 bg-white border-b border-[#EDEDED] dark:bg-gray-800 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.customer_details') }}</h3>
                </div>
                <div class="p-6 bg-white flex flex-col gap-6 dark:bg-gray-900">
                    {{-- Customer Row --}}
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-blue-50 rounded-xl dark:bg-blue-900/20">
                            <x-heroicon-o-user class="h-6 w-6 text-blue-500 dark:text-blue-400" />
                        </div>
                        <div class="flex flex-1 items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-base font-semibold text-gray-950 dark:text-white">{{ $displayName }}</span>
                                @if ($customerDeleted)
                                <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                    {{ __('admin.account_deleted') }}
                                </span>
                                @endif
                            </div>
                            @if($customerUrl = $this->getCustomerViewUrl())
                            <a href="{{ $customerUrl }}" wire:navigate class="shrink-0 text-sm font-normal text-primary-600 dark:text-primary-400 hover:underline">
                                {{ __('admin.customer_id') }}: CUST-{{ str_pad((string) ($customer?->id ?? $booking->user_id ?? 0), 4, '0', STR_PAD_LEFT) }}
                            </a>
                            @else
                            <span class="shrink-0 text-sm font-normal text-primary-600 dark:text-primary-400">
                                {{ __('admin.customer_id') }}: CUST-{{ str_pad((string) ($customer?->id ?? $booking->user_id ?? 0), 4, '0', STR_PAD_LEFT) }}
                            </span>
                            @endif
                        </div>
                    </div>

                    {{-- Divider --}}
                    <div class="border-t border-[#EDEDED] dark:border-gray-700"></div>

                    {{-- Contact Information --}}
                    <div class="flex flex-col gap-6">
                        <h4 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.contact_information') }}</h4>
                        <div class="bv-contact-row flex gap-6">
                            <div class="flex flex-1 items-center gap-4">
                                <div class="p-3 bg-gray-100 rounded-xl dark:bg-gray-700">
                                    <x-heroicon-o-phone class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.phone') }}</p>
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $displayPhone ?: '-' }}</p>
                                </div>
                            </div>
                            <div class="flex flex-1 items-center gap-4">
                                <div class="p-3 bg-gray-100 rounded-xl dark:bg-gray-700">
                                    <x-heroicon-o-envelope class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.email') }}</p>
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $displayEmail ?: '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Payment Status / Method / Source — 3 separate cards --}}
            <div class="bv-payment-cards grid grid-cols-3 gap-7">
                {{-- Payment Status --}}
                @if($paymentUrl = $this->getPaymentViewUrl())
                <a href="{{ $paymentUrl }}" wire:navigate class="payment-status-card rounded-2xl border border-[#EDEDED] p-5 bg-white flex flex-col gap-4 transition dark:bg-gray-900 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.payment_status') }}</p>
                    <div class="flex items-center gap-2">
                        <div class="p-3 bg-gray-100 rounded-xl dark:bg-gray-700">
                            <x-heroicon-o-check-circle class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                        </div>
                        <span class="text-base font-medium text-gray-950 dark:text-white">{{ $booking->payment_status->label() }}</span>
                    </div>
                    <div class="payment-status-card-link flex items-center gap-1 text-xs font-medium">
                        <span>{{ __('admin.view_payment_details') }}</span>
                        <x-heroicon-o-chevron-right class="h-3 w-3" />
                    </div>
                </a>
                @else
                <div class="rounded-2xl border border-[#EDEDED] p-5 bg-white flex flex-col gap-4 dark:bg-gray-900 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.payment_status') }}</p>
                    <div class="flex items-center gap-2">
                        <div class="p-3 bg-gray-100 rounded-xl dark:bg-gray-700">
                            <x-heroicon-o-check-circle class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                        </div>
                        <span class="text-base font-medium text-gray-950 dark:text-white">{{ $booking->payment_status->label() }}</span>
                    </div>
                </div>
                @endif

                {{-- Payment Method --}}
                <div class="rounded-2xl border border-[#EDEDED] p-5 bg-white flex flex-col gap-4 dark:bg-gray-900 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.payment_method') }}</p>
                    <div class="flex items-center gap-2">
                        <div class="p-3 bg-gray-100 rounded-xl dark:bg-gray-700">
                            <x-heroicon-o-credit-card class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                        </div>
                        <span class="text-base font-medium text-gray-950 dark:text-white">{{ $booking->payment_methods_summary }}</span>
                    </div>
                </div>

                {{-- Booking Source --}}
                <div class="rounded-2xl border border-[#EDEDED] p-5 bg-white flex flex-col gap-4 dark:bg-gray-900 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.booking_source') }}</p>
                    <div class="flex items-center gap-2">
                        <div class="p-3 bg-gray-100 rounded-xl dark:bg-gray-700">
                            <x-heroicon-o-device-phone-mobile class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                        </div>
                        <span class="text-base font-medium text-gray-950 dark:text-white">{{ $booking->booking_source->label() }}</span>
                    </div>
                </div>
            </div>

            {{-- Booking Details --}}
            <div class="rounded-2xl border border-[#EDEDED] overflow-hidden dark:border-gray-700">
                <div class="p-6 bg-white border-b border-[#EDEDED] dark:bg-gray-800 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.booking_details') }}</h3>
                </div>
                <div class="px-5 pt-5 pb-3 bg-white dark:bg-gray-900">
                    <div class="bv-booking-details-grid grid grid-cols-3 gap-y-5 text-sm">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.booking_date_time') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->created_at->setTimezone($timezone)->format('M d, Y • H:i') }} {{ $tzAbbr }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.check_in') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->check_in->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.check_out') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->check_out->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.total_nights_label') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->total_nights }} {{ __('admin.nights') }} / {{ $booking->total_nights + 1 }} {{ __('admin.days') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.guests') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->adults }} {{ __('admin.adults') }}, {{ $booking->children }} {{ __('admin.children') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.rooms') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->booked_rooms }} {{ __('admin.room') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.room_type') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $roomType?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.room_number') }}</p>
                            @php
                                $assignedNumbers = $booking->roomAssignments->pluck('room.room_number')->filter()->join(', ');
                            @endphp
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                                {{ filled($assignedNumbers) ? $assignedNumbers : ($booking->room_number ?? '-') }}
                            </p>
                        </div>
                        @if ($booking->actual_checkout_at)
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.actual_checkout_at') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->actual_checkout_at->setTimezone($timezone)->format('M d, Y • H:i') }} {{ $tzAbbr }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cancellation Details (only if cancelled) --}}
            @if ($booking->status->value === 'cancelled')
            <div class="rounded-2xl border border-[#EDEDED] overflow-hidden dark:border-gray-700">
                <div class="p-6 bg-white border-b border-[#EDEDED] dark:bg-gray-800 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.cancellation_details') }}</h3>
                </div>
                <div class="p-6 bg-white dark:bg-gray-900">
                    <div class="bv-cancellation-grid grid grid-cols-2 gap-y-5 text-sm">
                        @if ($booking->cancelled_at)
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.cancelled_at') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->cancelled_at->setTimezone($timezone)->format('M d, Y • H:i') }} {{ $tzAbbr }}</p>
                        </div>
                        @endif
                        @if ($booking->cancellation_reason)
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-400">{{ __('admin.cancellation_reason') }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $booking->cancellation_reason }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Right Column (1/3) --}}
        <div class="space-y-7">
            {{-- Financial Details --}}
            <div class="rounded-2xl border border-[#EDEDED] overflow-hidden dark:border-gray-700">
                <div class="p-6 bg-white border-b border-[#EDEDED] dark:bg-gray-800 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.financial_details') }}</h3>
                </div>
                <div class="p-6 bg-white dark:bg-gray-900">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.subtotal') }}</span>
                            <span class="text-gray-950 dark:text-white">{{ $currency }}{{ number_format((float) $booking->base_amount, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.tax_and_fees') }}</span>
                            <span class="text-gray-950 dark:text-white">{{ $currency }}{{ number_format((float) $booking->tax_amount, 2) }}</span>
                        </div>
                        @if ((float) $booking->discount_amount > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ __('admin.coupon_discount') }}
                                @if ($booking->promoCode)
                                <span class="ml-1 font-mono text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $booking->promoCode->code }}</span>
                                @elseif ($booking->coupon)
                                <span class="ml-1 font-mono text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $booking->coupon->code }}</span>
                                @endif
                            </span>
                            <span class="text-red-600 dark:text-red-400">-{{ $currency }}{{ number_format((float) $booking->discount_amount, 2) }}</span>
                        </div>
                        @endif
                        <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-950 dark:text-white">{{ __('admin.total_amount') }}</span>
                                <span class="font-bold text-gray-950 dark:text-white">{{ $currency }}{{ number_format((float) $booking->total_amount, 2) }}</span>
                            </div>
                        </div>

                        @php
                        // Cancelled: use the real settled amounts from refund_inputs (0 if nothing
                        // retained), not the stale pre-cancellation commission_amount/check-in formula.
                        $isCancelled = $booking->status->value === 'cancelled';
                        $displayedCommission = $isCancelled
                        ? (float) ($booking->refund_inputs['commission'] ?? 0)
                        : (float) $booking->commission_amount;
                        $displayedPartnerEarning = $isCancelled
                        ? (float) ($booking->refund_inputs['wallet_credit'] ?? 0)
                        : app(\App\Services\CommissionService::class)->calculateCheckInPartnerCredit($booking);
                        @endphp
                        <div class="mt-1 space-y-3">
                            {{-- Admin Commission card --}}
                            <div class="flex items-center gap-3 rounded-xl bg-blue-50 p-4 dark:bg-blue-900/20">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500">
                                    <x-heroicon-o-computer-desktop class="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.admin_commission') }}</p>
                                    <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $currency }}{{ number_format($displayedCommission, 2) }}</p>
                                </div>
                            </div>
                            {{-- Partner Earning card --}}
                            <div class="flex items-center gap-3 rounded-xl bg-green-50 p-4 dark:bg-green-900/20">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-500">
                                    <x-heroicon-o-banknotes class="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.partner_earning') }}</p>
                                    <p class="text-lg font-bold text-green-600 dark:text-green-400">{{ $currency }}{{ number_format($displayedPartnerEarning + (! $isCancelled ? $manualRoomPortion : 0), 2) }}</p>
                                    @if (! $isCancelled && $manualRoomPortion > 0)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('admin.partner_earning_breakdown', ['payout' => $currency.number_format($displayedPartnerEarning, 2), 'cash' => $currency.number_format($manualRoomPortion, 2)]) }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($totalPaid > 0)
                        <div class="border-t border-gray-200 pt-3 dark:border-gray-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">{{ __('admin.amount_paid') }}</span>
                                <span class="font-medium text-green-600 dark:text-green-400">{{ $currency }}{{ number_format($totalPaid, 2) }}</span>
                            </div>
                            @if ($manualCollected > 0)
                            <div class="flex items-center justify-between pl-3">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('admin.collected_online') }}</span>
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $currency }}{{ number_format($onlineCollected, 2) }}</span>
                            </div>
                            @endif
                            @if ($transactionId)
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('admin.transaction_id') }}</span>
                                <span class="break-all text-right font-mono text-xs font-medium text-gray-950 dark:text-white">{{ $transactionId }}</span>
                            </div>
                            @endif
                            @if ($booking->payment_status === \App\Enums\PaymentStatus::Partial)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">{{ __('admin.remaining_balance') }}</span>
                                <span class="font-medium text-orange-600 dark:text-orange-400">{{ $currency }}{{ number_format((float) $booking->total_amount - $totalPaid, 2) }}</span>
                            </div>
                            @if ($booking->property?->advance_percentage)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">{{ __('admin.advance_paid') }}</span>
                                <span class="font-medium text-gray-950 dark:text-white">{{ number_format((float) $booking->property->advance_percentage, 0) }}%</span>
                            </div>
                            @endif
                            @endif
                        </div>
                        @endif

                        {{-- Cancellation Settlement --}}
                        @if ($booking->status->value === 'cancelled' && $refunds->count() > 0)
                        @php
                        $refund = $refunds->first();
                        $refundPct = (float) $refund->refund_percentage;
                        $refundAmount = (float) $refund->amount;
                        $cancellationFeePct = max(0, 100 - $refundPct);
                        $cancellationCharges = $totalPaid - $refundAmount;
                        $netRetained = $cancellationCharges;
                        @endphp
                        <div class="mt-4 rounded-2xl bg-[#F7F7F7] p-4 flex flex-col gap-4 dark:bg-gray-800/50">
                            <div class="flex w-full bg-white border border-[#EDEDED] rounded-xl px-4 py-2.5 items-center gap-2 dark:bg-gray-900 dark:border-gray-800">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                                </svg>
                                <h4 class="text-xs font-semibold text-gray-950 dark:text-white">{{ __('admin.cancellation_settlement') }}</h4>
                            </div>
                            <div class="space-y-3 px-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.cancellation_policy_applied', ['percentage' => number_format($cancellationFeePct, 0)]) }}</p>
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="text-red-600 dark:text-red-400">{{ __('admin.cancellation_charges') }}</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">-{{ $currency }}{{ number_format($cancellationCharges, 2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-gray-200 pt-2 dark:border-gray-700">
                                        <span class="font-semibold text-gray-950 dark:text-white">{{ __('admin.net_amount_retained') }}</span>
                                        <span class="font-bold text-gray-950 dark:text-white">{{ $currency }}{{ number_format($netRetained, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Refund Details (only if cancelled) --}}
            @if ($booking->status->value === 'cancelled' && $refunds->count() > 0)
            <div class="rounded-2xl border border-[#EDEDED] overflow-hidden dark:border-gray-700">
                <div class="p-6 bg-white border-b border-[#EDEDED] dark:bg-gray-800 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.refund_details') }}</h3>
                </div>
                <div class="p-6 bg-white dark:bg-gray-900">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.refund_amount') }}</span>
                            <span class="text-green-600 dark:text-green-400">{{ $currency }}{{ number_format((float) $refunds->sum('amount'), 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.refund_status') }}</span>
                            <span class="font-medium text-gray-950 dark:text-white">{{ ucfirst($refunds->first()->status->value) }}</span>
                        </div>
                        @if ($refunds->first()->refund_id)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.refund_id') }}</span>
                            <span class="font-medium text-gray-950 dark:text-white">{{ $refunds->first()->refund_id }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
