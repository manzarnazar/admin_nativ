@php
    $booking = $booking ?? null;
    $roomId = $get('room_type_id');
    $checkIn = $get('check_in');
    $checkOut = $get('check_out');
    $bookedRooms = (int) ($get('booked_rooms') ?? 1);

    $baseAmount = 0;
    $taxAmount = 0;
    $totalAmount = 0;
    $nights = 0;
    $availableRooms = null;

    $user = auth()->user();
    $countryId = $user->role === \App\Enums\UserRole::Partner && $user->partner
        ? \App\Support\PartnerContext::currentCountryId($user->partner)
        : $user->current_country_id;
    $currency = \App\Models\Country::where('id', $countryId)->value('currency_symbol') ?? '$';

    // When editing an existing booking, use stored values instead of recalculating
    if ($booking) {
        $nights = $booking->total_nights;
        $baseAmount = (float) $booking->base_amount;
        $taxAmount = (float) $booking->tax_amount;
        $totalAmount = (float) $booking->total_amount;

        // Load all successful payments for total paid calculation
        $successfulPayments = $booking->payments()
            ->where('status', \App\Enums\PaymentTransactionStatus::Success)
            ->get();
        $totalPaid = $successfulPayments->sum('amount');
        $firstPayment = $successfulPayments->first();
    } elseif ($roomId && $checkIn && $checkOut) {
        $propertyRoom = \App\Models\PropertyRoom::find($roomId);
        if ($propertyRoom) {
            $nights = max(1, (int) \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut)));
            $pricing = app(\App\Services\BookingService::class)->calculatePricing(
                $propertyRoom,
                $nights,
                $bookedRooms,
                $countryId,
            );
            $baseAmount = $pricing['base_amount'];
            $taxAmount = $pricing['tax_amount'];
            $totalAmount = $pricing['total_amount'];
            $availableRooms = app(\App\Services\BookingService::class)->getAvailableRooms($propertyRoom, $checkIn, $checkOut);
        }
    }
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    {{-- Header --}}
    <div class="flex items-center gap-2 bg-gray-50 px-4 py-3 dark:bg-gray-800">
        <x-heroicon-o-calculator class="h-4 w-4 text-gray-600 dark:text-gray-400" />
        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.pricing_summary') }}</span>
    </div>

    {{-- Availability Badge --}}
    @if ($availableRooms !== null)
        <div class="px-4 pt-3">
            @if ($availableRooms > 0)
                <div class="flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 dark:bg-green-950/30">
                    <x-heroicon-o-check-circle class="h-4 w-4 text-green-600 dark:text-green-400" />
                    <span class="text-xs font-medium text-green-700 dark:text-green-300">
                        {{ $availableRooms }} {{ __('admin.rooms_available') }}
                    </span>
                </div>
            @else
                <div class="flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 dark:bg-red-950/30">
                    <x-heroicon-o-x-circle class="h-4 w-4 text-red-600 dark:text-red-400" />
                    <span class="text-xs font-medium text-red-700 dark:text-red-300">
                        {{ __('admin.no_rooms_available') }}
                    </span>
                </div>
            @endif
        </div>
    @endif

    {{-- Content --}}
    <div class="space-y-2.5 px-4 py-4 text-sm">
        <div class="flex items-center justify-between">
            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.base_rate') }} ({{ $nights }} {{ __('admin.nights') }})</span>
            <span class="font-medium text-gray-950 dark:text-white">{{ $currency }}{{ number_format($baseAmount, 2) }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-gray-500 dark:text-gray-400">{{ __('admin.tax_and_fees') }}</span>
            <span class="font-medium text-gray-950 dark:text-white">{{ $currency }}{{ number_format($taxAmount, 2) }}</span>
        </div>
        <div class="border-t border-gray-200 pt-2.5 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-950 dark:text-white">{{ __('admin.total_amount') }}</span>
                <span class="text-base font-bold text-primary-600 dark:text-primary-400">{{ $currency }}{{ number_format($totalAmount, 2) }}</span>
            </div>
        </div>

        {{-- Payment breakdown for existing bookings with payments --}}
        @if (isset($successfulPayments) && $successfulPayments->count() > 0)
            <div class="border-t border-gray-200 pt-2.5 dark:border-gray-700 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.amount_paid') }}</span>
                    <span class="font-medium text-green-600 dark:text-green-400">{{ $currency }}{{ number_format((float) $totalPaid, 2) }}</span>
                </div>
                @if ($totalPaid < $totalAmount)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('admin.remaining_balance') }}</span>
                        <span class="font-medium text-orange-600 dark:text-orange-400">{{ $currency }}{{ number_format((float) ($totalAmount - $totalPaid), 2) }}</span>
                    </div>
                @endif
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.payment_via') }}</span>
                    <span class="font-medium text-gray-950 dark:text-white">
                        {{ $successfulPayments->map(fn($p) => $p->gateway_type->value === 'manual' ? ucfirst($p->metadata['payment_method'] ?? $p->metadata['method'] ?? $p->metadata['type'] ?? $p->metadata['payment_type'] ?? $p->gateway_response['payment_method'] ?? 'Manual') : ucfirst($p->gateway_type->value))->unique()->join(' + ') }}
                    </span>
                </div>
                @if ($firstPayment)
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.payment_status') }}</span>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                        {{ $firstPayment->status === \App\Enums\PaymentTransactionStatus::Success ? 'bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                        {{ ucfirst($firstPayment->status->value) }}
                    </span>
                </div>
                @endif
            </div>
        @elseif (isset($booking) && $booking && $booking->payment_status === \App\Enums\PaymentStatus::Partial)
            {{-- Fallback: booking is marked partial but no successful payment found --}}
            <div class="border-t border-gray-200 pt-2.5 dark:border-gray-700">
                <div class="flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-2 dark:bg-orange-950/30">
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                    <span class="text-xs font-medium text-orange-700 dark:text-orange-300">
                        {{ __('admin.partial_payment_pending') }}
                    </span>
                </div>
            </div>
        @endif
    </div>
</div>
