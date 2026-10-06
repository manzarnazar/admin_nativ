@php
    $payment = $this->getPayment();
    $booking = $payment->booking;
    $currency = $payment->currency;
    $refunds = $payment->refunds;
    $timezone = $this->getTimezone();
    $timezoneAbbr = \Carbon\Carbon::now($timezone)->format('T');

    // Load all successful payments for this booking
    $allPayments = $booking->payments()->where('status', \App\Enums\PaymentTransactionStatus::Success)->get();
    $totalPaid = $allPayments->sum('amount');
    $totalAmount = $booking->total_amount;

    $statusColor = match($payment->status->value) {
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'processing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        'failed', 'flagged' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };

    $gatewayColor = match($payment->gateway_type->value) {
        'razorpay' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
        'stripe' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        'flutterwave' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
        'manual' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        default => 'bg-gray-100 text-gray-700',
    };

    $refundStatusColor = fn($status) => match($status) {
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'processing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };
@endphp

<x-filament-panels::page>
    <style>
        .master-detail-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .master-detail-grid { grid-template-columns: 2fr 1fr; }
        }
        .timeline-item {
            position: relative;
            padding-left: 3.5rem;
            min-height: 2.5rem;
        }
        .timeline-marker {
            position: absolute;
            left: 0;
            top: 0;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        .timeline-marker-neutral {
            background: #ffffff;
            border: 2px solid #d1d5db;
            color: #9ca3af;
        }
        .dark .timeline-marker-neutral {
            background: #1f2937;
            border-color: #4b5563;
            color: #6b7280;
        }
        .timeline-marker-success {
            background: #10b981;
            border: 2px solid #10b981;
            color: #ffffff;
        }
        .timeline-marker-error {
            background: #ef4444;
            border: 2px solid #ef4444;
            color: #ffffff;
        }
        .timeline-item + .timeline-item {
            margin-top: 1.5rem;
        }
        .timeline-item + .timeline-item::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: -1.5rem;
            bottom: 1.5rem;
            width: 2px;
            background: #e5e7eb;
        }
        .dark .timeline-item + .timeline-item::before {
            background: #374151;
        }
        .copy-btn {
            opacity: 0;
            transition: opacity 0.15s;
        }
        .copy-wrapper:hover .copy-btn {
            opacity: 1;
        }
    </style>

    <div class="master-detail-grid">
        {{-- LEFT COLUMN (2/3): Summary + Timeline + Response --}}
        <div class="space-y-6">

            {{-- Payment Summary --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.payment_summary') }}</h2>
                </div>
                <div class="p-5">
                    {{-- Amount & Status Hero --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div class="flex items-baseline gap-3">
                            <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ $currency }} {{ number_format((float) $payment->amount, 2) }}
                            </span>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusColor }}">
                                {{ $payment->status->label() }}
                            </span>
                        </div>
                        @if ($booking)
                            <a href="{{ \App\Filament\Pages\BookingView::getUrl(['record' => $booking->id]) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                {{ __('admin.booking') }} {{ $booking->booking_number }}
                            </a>
                        @endif
                    </div>

                    {{-- Meta Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('admin.payment_id') }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">#{{ $payment->id }}</span>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('admin.gateway') }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $gatewayColor }}">
                                {{ $payment->gateway_type->label() }}
                            </span>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('admin.type') }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $payment->payment_type->value === 'full' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                {{ $payment->payment_type->label() }}
                            </span>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('admin.currency') }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $currency }}</span>
                        </div>
                    </div>

                    {{-- Additional Details --}}
                    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-800">
                        <div class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                            @if ($totalPaid < $totalAmount)
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.remaining') }}</span>
                                    <span class="ml-2 font-semibold text-orange-600 dark:text-orange-400">{{ $currency }} {{ number_format((float) ($totalAmount - $totalPaid), 2) }}</span>
                                </div>
                            @endif
                            @if ($payment->converted_amount)
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.converted') }}</span>
                                    <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ number_format((float) $payment->converted_amount, 2) }}</span>
                                </div>
                            @endif
                            @if ($allPayments->count() > 1)
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.total_paid') }} ({{ $allPayments->count() }} payments)</span>
                                    <span class="ml-2 font-semibold text-green-600 dark:text-green-400">{{ $currency }} {{ number_format((float) $totalPaid, 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- All Payments for Booking --}}
            @if ($allPayments->count() > 1)
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.all_payments') }} ({{ $allPayments->count() }})</h2>
                </div>
                <div class="p-5 space-y-3">
                    @foreach ($allPayments as $index => $p)
                    @php
                        $isCurrent = $p->id === $payment->id;
                        $rowClasses = 'flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800 '
                            . ($isCurrent ? 'ring-2 ring-indigo-500' : 'transition-colors hover:bg-gray-100 dark:hover:bg-gray-700/60 cursor-pointer');
                    @endphp
                    <{{ $isCurrent ? 'div' : 'a' }}
                        @if (! $isCurrent)
                            href="{{ \App\Filament\Resources\PaymentResource::getUrl('view', ['record' => $p->id]) }}"
                            wire:navigate
                        @endif
                        class="{{ $rowClasses }}"
                    >
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $currency }} {{ number_format((float) $p->amount, 2) }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    @php
                                        $displayMethod = $p->gateway_type->value === 'manual'
                                            ? ucfirst($p->metadata['payment_method'] ?? $p->metadata['method'] ?? $p->metadata['type'] ?? $p->metadata['payment_type'] ?? $p->gateway_response['payment_method'] ?? 'Manual')
                                            : ucfirst($p->gateway_type->value);

                                        // Add transaction ID for UPI payments
                                        if ($p->gateway_type->value === 'manual' && strtolower($displayMethod) === 'upi') {
                                            $txnId = $p->metadata['transaction_id'] ?? $p->gateway_response['transaction_id'] ?? null;
                                            if ($txnId) {
                                                $displayMethod .= " ({$txnId})";
                                            }
                                        }
                                    @endphp
                                    {{ $displayMethod }}
                                    · {{ $p->paid_at?->setTimezone($timezone)->format('M d, Y · H:i') ?? '—' }} {{ $timezoneAbbr }}
                                </span>
                            </div>
                        </div>
                        @if ($isCurrent)
                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">{{ __('admin.viewing') }}</span>
                        @else
                        <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" />
                        @endif
                    </{{ $isCurrent ? 'div' : 'a' }}>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Refunds Section --}}
            @if ($refunds->count() > 0)
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.refunds') }} ({{ $refunds->count() }})</h2>
                </div>
                <div class="p-5 space-y-3">
                    @foreach ($refunds as $refund)
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $currency }} {{ number_format((float) $refund->amount, 2) }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $refund->refund_id }} · {{ $refund->processed_at ? $refund->processed_at->setTimezone($timezone)->format('M d, Y · H:i').' '.$timezoneAbbr : 'Processing' }}
                                </span>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $refundStatusColor($refund->status->value) }}">
                            {{ $refund->status->label() }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Payment Timeline --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.payment_timeline') }}</h2>
                </div>
                <div class="p-5">
                    <div class="space-y-1">
                        {{-- Created --}}
                        <div class="timeline-item">
                            <div class="timeline-marker timeline-marker-success">
                                <x-heroicon-o-check class="h-4 w-4" />
                            </div>
                            <div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">{{ __('admin.created') }}</span>
                                <span class="block text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ $payment->created_at?->setTimezone($timezone)->format('M d, Y · H:i') ?? '—' }} {{ $payment->created_at ? $timezoneAbbr : '' }}
                                </span>
                            </div>
                        </div>

                        {{-- Paid At --}}
                        @if ($payment->paid_at)
                            <div class="timeline-item">
                                <div class="timeline-marker timeline-marker-success">
                                    <x-heroicon-o-check class="h-4 w-4" />
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">{{ __('admin.paid') }}</span>
                                    <span class="block text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ $payment->paid_at->setTimezone($timezone)->format('M d, Y · H:i') }} {{ $timezoneAbbr }}
                                    </span>
                                </div>
                            </div>
                        @endif

                        {{-- Failed At --}}
                        @if ($payment->failed_at)
                            <div class="timeline-item">
                                <div class="timeline-marker timeline-marker-error">
                                    <x-heroicon-o-x-mark class="h-4 w-4" />
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">{{ __('admin.failed') }}</span>
                                    <span class="block text-sm font-semibold text-red-600 dark:text-red-400">
                                        {{ $payment->failed_at->setTimezone($timezone)->format('M d, Y · H:i') }} {{ $timezoneAbbr }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Gateway Response (full width, collapsible) --}}
            {{-- Uncomment the following section to view raw gateway JSON for debugging --}}
            {{-- @if ($payment->gateway_response)
                <div x-data="{ open: false }" class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Gateway Response</h2>
                        <x-heroicon-o-chevron-down class="h-4 w-4 text-gray-400 transition-transform" ::class="{ 'rotate-180': open }" />
                    </button>
                    <div x-show="open" x-collapse>
                        <div class="p-5">
                            <pre class="max-h-80 overflow-auto rounded-lg bg-gray-50 p-4 text-xs font-mono text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ json_encode($payment->gateway_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                </div>
            @endif --}}

        </div>

        {{-- RIGHT COLUMN (1/3): Compact Gateway Info --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.gateway_information') }}</h2>
                </div>
                <div class="p-4 space-y-4">
                    <div class="copy-wrapper relative group" x-data="{{ json_encode(['copied' => false, 'val' => $payment->gateway_order_id ?? '']) }}">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('admin.order_id') }}</span>
                        <code class="block text-xs font-mono text-gray-700 dark:text-gray-300 break-all pr-8">{{ $payment->gateway_order_id ?? '—' }}</code>
                        <button type="button" @click="if(navigator.clipboard){navigator.clipboard.writeText(val).then(()=>{copied=true;setTimeout(()=>copied=false,2000)})}else{let t=document.createElement('textarea');t.value=val;document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t);copied=true;setTimeout(()=>copied=false,2000)};$el.blur()" class="copy-btn absolute right-0 top-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <x-heroicon-o-check x-show="copied" class="h-4 w-4 text-green-500" />
                            <x-heroicon-o-document-duplicate x-show="!copied" class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="copy-wrapper relative group" x-data="{{ json_encode(['copied' => false, 'val' => $payment->gateway_payment_id ?? '']) }}">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('admin.payment_id') }}</span>
                        <code class="block text-xs font-mono text-gray-700 dark:text-gray-300 break-all pr-8">{{ $payment->gateway_payment_id ?? '—' }}</code>
                        <button type="button" @click="if(navigator.clipboard){navigator.clipboard.writeText(val).then(()=>{copied=true;setTimeout(()=>copied=false,2000)})}else{let t=document.createElement('textarea');t.value=val;document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t);copied=true;setTimeout(()=>copied=false,2000)};$el.blur()" class="copy-btn absolute right-0 top-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <x-heroicon-o-check x-show="copied" class="h-4 w-4 text-green-500" />
                            <x-heroicon-o-document-duplicate x-show="!copied" class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="copy-wrapper relative group" x-data="{{ json_encode(['copied' => false, 'val' => $payment->gateway_event_id ?? '']) }}">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('admin.event_id') }}</span>
                        <code class="block text-xs font-mono text-gray-700 dark:text-gray-300 break-all pr-8">{{ $payment->gateway_event_id ?? '—' }}</code>
                        <button type="button" @click="if(navigator.clipboard){navigator.clipboard.writeText(val).then(()=>{copied=true;setTimeout(()=>copied=false,2000)})}else{let t=document.createElement('textarea');t.value=val;document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t);copied=true;setTimeout(()=>copied=false,2000)};$el.blur()" class="copy-btn absolute right-0 top-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <x-heroicon-o-check x-show="copied" class="h-4 w-4 text-green-500" />
                            <x-heroicon-o-document-duplicate x-show="!copied" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
