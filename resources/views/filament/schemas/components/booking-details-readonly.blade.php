@php
$booking = $booking ?? null;
$customer = $booking?->customer;
$user = auth()->user();
$countryId = $user->role === \App\Enums\UserRole::Partner && $user->partner
    ? \App\Support\PartnerContext::currentCountryId($user->partner)
    : $user->current_country_id;
$currency = \App\Models\Country::where('id', $countryId)->value('currency_symbol') ?? '$';
$hidePaymentCard = $hide_payment_card ?? false;

// Prefer guest_phone (filled at booking time) over the customer's current phone,
// so the modal always shows the contact used for this specific booking.
$phoneOnly = $booking?->guest_phone ?: $customer?->phone;
$dialCode = $booking?->guest_phone
? ($booking->guest_dial_code ?? '')
: ($customer?->dial_code ?? '');
$displayPhone = $phoneOnly
? trim(($dialCode ? $dialCode.' ' : '').\App\Support\DemoMode::maskPhone($phoneOnly))
: null;

$statusStyle = match($booking?->status?->value) {
'confirmed' => 'background-color: #e5faef; color: #20b364;',
'checked_in' => 'background-color: #dbeafe; color: #1d4ed8;',
'completed' => 'background-color: #f3f4f6; color: #6b7280;',
'cancelled' => 'background-color: #fee2e2; color: #dc2626;',
default => 'background-color: #f3f4f6; color: #6b7280;',
};

$paymentStyle = match($booking?->payment_status?->value) {
'paid' => 'background-color: #e5faef; color: #20b364;',
'unpaid' => 'background-color: #fef9c3; color: #a16207;',
'partial' => 'background-color: #dbeafe; color: #1d4ed8;',
'refunded' => 'background-color: #fee2e2; color: #dc2626;',
default => 'background-color: #f3f4f6; color: #6b7280;',
};
@endphp

@if ($booking)
<div style="display: flex; flex-direction: column; gap: 24px; padding-top: 4px;">

    {{-- Booking ID + Status --}}
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <span style="font-size: 16px; font-weight: 500; color: #1a73e8;">ID-{{ $booking->id }}</span>
        <span style="font-size: 14px; font-weight: 500; padding: 4px 12px; border-radius: 8px; {{ $statusStyle }}">
            {{ $booking->status->label() }}
        </span>
    </div>

    {{-- Customer Card --}}
    @if ($customer)
    <div style="border: 1px solid #ededed; border-radius: 16px; padding: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            {{-- Avatar 80x80 --}}
            <div style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; background-color: #e5e7eb; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                @if ($customer->avatar && str_starts_with($customer->avatar, 'avatars/'))
                <img src="{{ asset('storage/' . $customer->avatar) }}" alt="{{ $customer->name }}" style="width: 100%; height: 100%; object-fit: cover;" />
                @else
                <x-heroicon-o-user style="width: 36px; height: 36px; color: #9ca3af;" />
                @endif
            </div>

            {{-- Name + Contact --}}
            <div style="flex: 1; min-width: 0;">
                <p style="font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 8px 0;">{{ $customer->name }}</p>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #555;">
                        <x-heroicon-o-phone style="width: 16px; height: 16px; flex-shrink: 0;" />
                        {{ $displayPhone ?? '-' }}
                    </div>
                    @if ($customer->email)
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #555;">
                        <x-heroicon-o-envelope style="width: 16px; height: 16px; flex-shrink: 0;" />
                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ \App\Support\DemoMode::maskEmail($customer->email) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Customer ID --}}
            <div style="text-align: right; flex-shrink: 0;">
                <p style="font-size: 14px; font-weight: 500; color: #1a73e8; margin: 0 0 4px 0;">{{ __('admin.customer_id') }}</p>
                <p style="font-size: 20px; font-weight: 700; color: #111827; margin: 0;">#{{ str_pad((string) $customer->id, 2, '0', STR_PAD_LEFT) }}</p>
            </div>
        </div>
    </div>
    @endif

    {{-- Booking Details Card --}}
    <div style="border: 1px solid #ededed; border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 24px;">
        {{-- Section Header --}}
        <div style="background-color: #f7f7f7; border-radius: 12px; padding: 12px; display: flex; align-items: center; gap: 8px;">
            <x-heroicon-o-user-circle style="width: 24px; height: 24px; color: #555;" />
            <span style="font-size: 18px; font-weight: 600; color: #111827;">{{ __('admin.booking_details') }}</span>
        </div>

        {{-- Detail Rows --}}
        <div style="display: flex; flex-direction: column; gap: 24px; font-size: 14px; font-weight: 500;">

            {{-- Check-in / Check-out --}}
            <div style="display: flex; gap: 4px;">
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px;">
                    <span style="color: #555;">{{ __('admin.check_in') }}</span>
                    <span style="color: #111827;">{{ $booking->check_in->format('Y-m-d') }}</span>
                </div>
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px;">
                    <span style="color: #555;">{{ __('admin.check_out') }}</span>
                    <span style="color: #111827;">{{ $booking->check_out->format('Y-m-d') }}</span>
                </div>
            </div>

            {{-- Guests --}}
            <div style="display: flex; gap: 4px; align-items: center;">
                <span style="flex: 1; color: #555;">{{ __('admin.guests') }}</span>
                <span style="flex: 1; color: #111827;">{{ $booking->adults }} {{ __('admin.adults') }}, {{ $booking->children }} {{ __('admin.children') }}</span>
            </div>

            {{-- Rooms --}}
            <div style="display: flex; gap: 4px; align-items: center;">
                <span style="flex: 1; color: #555;">{{ __('admin.rooms') }}</span>
                <span style="flex: 1; color: #111827;">{{ $booking->booked_rooms }} {{ __('admin.room') }}</span>
            </div>

            {{-- Room Type --}}
            <div style="display: flex; gap: 4px; align-items: center;">
                <span style="flex: 1; color: #555;">{{ __('admin.room_type') }}</span>
                <span style="flex: 1; color: #111827;">{{ $booking->propertyRoom?->roomType?->name ?? '-' }}</span>
            </div>

            {{-- Room Number Badges --}}
            <div style="display: flex; gap: 4px; align-items: flex-start;">
                <span style="flex: 1; color: #555; padding-top: 4px;">{{ __('admin.room_number') }}</span>
                <div style="flex: 1; display: flex; flex-wrap: wrap; gap: 8px;">
                    @php $assignments = $booking->roomAssignments->filter(fn($a) => $a->room !== null); @endphp
                    @if ($assignments->isNotEmpty())
                    @foreach ($assignments as $assignment)
                    <span style="background-color: #e7f4fe; color: #2196f3; padding: 4px 8px; border-radius: 8px; font-size: 13px; font-weight: 500; white-space: nowrap;">
                        {{ $assignment->room->floor?->name ?? __('admin.floor') }} - {{ $assignment->room->room_number }}
                    </span>
                    @endforeach
                    @else
                    <span style="color: #111827;">-</span>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Payment Details Card — hidden when the Filament payment section is active below --}}
    @if (! $hidePaymentCard)
    <div style="border: 1px solid #ededed; border-radius: 16px; overflow: hidden;">
        <div style="padding: 16px; border-bottom: 1px solid #ededed;">
            <p style="font-size: 18px; font-weight: 600; color: #111827; margin: 0;">{{ __('admin.payment_details') }}</p>
        </div>
        <div style="padding: 16px; display: flex; flex-direction: column; gap: 16px;">
            {{-- Total Amount --}}
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 16px; color: #111827;">{{ __('admin.total_amount') }}</span>
                <span style="font-size: 18px; font-weight: 600; color: #111827;">{{ $currency }}{{ number_format((float) $booking->total_amount, 2) }}</span>
            </div>
            {{-- Divider --}}
            <div style="height: 1px; background-color: #ededed;"></div>
            {{-- Payment Status --}}
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 16px; color: #111827;">{{ __('admin.status') }}</span>
                <span style="font-size: 14px; font-weight: 500; padding: 4px 12px; border-radius: 8px; {{ $paymentStyle }}">
                    {{ $booking->payment_status->label() }}
                </span>
            </div>
        </div>
    </div>
    @endif

</div>
@endif