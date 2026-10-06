@php
    $payAtProperty = $get('pay_at_property');
    $advancePercentage = max(0, min(99, (int) ($get('advance_percentage') ?? 0)));
    $remainingPercentage = 100 - $advancePercentage;
@endphp

<div class="space-y-3">
    @if (! $payAtProperty)
        {{-- Toggle OFF: Full online payment message --}}
        <div class="flex items-center gap-3 rounded-lg bg-blue-50 px-4 py-3 dark:bg-blue-950/30">
            <x-heroicon-o-information-circle class="h-5 w-5 shrink-0 text-blue-500 dark:text-blue-400" />
            <span class="text-sm text-blue-700 dark:text-blue-300">
                {{ __('admin.full_payment_online_note') }}
            </span>
        </div>
    @elseif ($advancePercentage > 0)
        {{-- Toggle ON with percentage entered --}}
        <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
            <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('admin.remaining_percentage_at_property') }}
            </span>
            <div class="text-right">
                <span class="text-lg font-bold text-gray-950 dark:text-white">{{ $remainingPercentage }}%</span>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.of_total_booking_value') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3 rounded-lg bg-red-50 px-4 py-3 dark:bg-red-950/30">
            <x-heroicon-o-information-circle class="h-5 w-5 shrink-0 text-red-500 dark:text-red-400" />
            <span class="text-sm text-red-700 dark:text-red-300">
                <span class="font-semibold">{{ __('admin.note') }}:</span>
                {{ __('admin.payment_config_note', ['advance' => $advancePercentage, 'remaining' => $remainingPercentage]) }}
            </span>
        </div>
    @endif
</div>
