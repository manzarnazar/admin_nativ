@php
    $totalValue = \App\Models\Booking::where('promo_code_id', $record->id)->sum('discount_amount');

    $discountText = $record->discount_type === \App\Enums\PromoDiscountType::Percentage
        ? number_format($record->discount_value) . '% Off'
        : $currency . number_format($record->discount_value, 2);

    $cityNames = $record->cities->pluck('name')->join(', ') ?: 'All Cities';
@endphp

<div class="space-y-4 px-1 pb-1">

    {{-- Code badge + Auto Apply --}}
    <div class="flex items-center gap-2 flex-wrap -mt-1">
        <span style="background:#111827;color:#fff;font-weight:700;padding:3px 12px;border-radius:6px;font-size:13px;letter-spacing:0.5px;">
            {{ $record->code }}
        </span>
        @if($record->is_auto_apply)
            <span class="text-sm text-gray-500">· Auto Apply</span>
        @endif
    </div>

    {{-- Description box --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('admin.description') }}</p>
        <p class="text-sm text-gray-800 dark:text-gray-200">{{ $record->description ?? '—' }}</p>
    </div>

    {{-- 3 Stats --}}
    <div class="grid grid-cols-3 divide-x divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
        <div class="p-4">
            <p class="mb-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <x-heroicon-o-tag class="h-3.5 w-3.5" /> {{ __('admin.discount') }}
            </p>
            <p class="text-base font-bold text-gray-900 dark:text-white">{{ $discountText }}</p>
        </div>
        <div class="p-4">
            <p class="mb-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <x-heroicon-o-arrow-path class="h-3.5 w-3.5" /> {{ __('admin.total_redemptions') }}
            </p>
            <p class="text-base font-bold text-gray-900 dark:text-white">{{ number_format($record->used_count) }}</p>
        </div>
        <div class="p-4">
            <p class="mb-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <x-heroicon-o-banknotes class="h-3.5 w-3.5" /> {{ __('admin.total_value') }}
            </p>
            <p class="text-base font-bold text-gray-900 dark:text-white">{{ $currency }}{{ number_format($totalValue, 0) }}</p>
        </div>
    </div>

    {{-- Commercial Rules + Targeting --}}
    <div class="grid grid-cols-2 gap-4">

        {{-- Commercial Rules --}}
        <div>
            <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-document-text class="h-4 w-4 text-gray-500" /> {{ __('admin.commercial_rules') }}
            </h4>
            <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.discount_type') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->discount_type->label() }}</span>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.value') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $discountText }}</span>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.max_discount_cap') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $record->max_discount_cap ? $currency . number_format($record->max_discount_cap, 0) : '—' }}
                    </span>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.minimum_booking_amount') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $record->min_booking_amount ? $currency . number_format($record->min_booking_amount, 0) : '—' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Targeting --}}
        <div>
            <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-globe-alt class="h-4 w-4 text-gray-500" /> {{ __('admin.targeting') }}
            </h4>
            <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.city') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $cityNames }}</span>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.customer_segments') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->customer_segment->label() }}</span>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.first_booking_only') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $record->is_first_booking_only ? __('admin.yes') : __('admin.no') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Validity + Usage Limits --}}
    <div class="grid grid-cols-2 gap-4">

        {{-- Validity --}}
        <div>
            <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-calendar-days class="h-4 w-4 text-gray-500" /> {{ __('admin.validity') }}
            </h4>
            <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.start_date') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->start_date->format('M d, Y') }}</span>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.end_date') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->end_date->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Usage Limits --}}
        <div>
            <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-users class="h-4 w-4 text-gray-500" /> {{ __('admin.usage_limits') }}
            </h4>
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-500">{{ __('admin.total_global_user') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($record->usage_limit) }}</span>
                </div>
            </div>
        </div>
    </div>

</div>
