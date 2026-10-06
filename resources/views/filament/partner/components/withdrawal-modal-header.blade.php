@php
$maskedAccount = $property?->bank_account_number
? '****' . substr($property->bank_account_number, -4)
: '—';
@endphp

{{-- Wallet Balance Card --}}
<div class="mb-4 rounded-xl p-5" style="background-color: #1e1e2e;">
    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl" style="background-color: rgba(255,255,255,0.12);">
        <x-heroicon-o-wallet class="h-5 w-5 text-white" />
    </div>
    <p class="text-sm font-medium text-gray-400">{{ __('admin.wallet_balance') }}</p>
    <p class="text-2xl font-bold text-white">{{ $symbol }}{{ number_format($available, 2) }}</p>
</div>

{{-- Payout Destination --}}
<div class="mb-2">
    <div class="mb-3 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.payout_destination') }}</span>
        <a href="{{ $bankEditUrl }}" wire:navigate target="_blank" class="flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
            {{ __('admin.edit_bank_details') }}
            <x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5" />
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.bank_name') }}</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $property?->bank_name ?? '—' }}</span>
        </div>
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.account_number') }}</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $maskedAccount }}</span>
        </div>
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.account_holder') }}</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $property?->bank_account_holder ?? '—' }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.bank_code') }}</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $property?->bank_code ?? '—' }}</span>
        </div>
    </div>
</div>