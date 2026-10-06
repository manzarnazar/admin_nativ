<div class="grid grid-cols-3 gap-6">
    {{-- Days Before --}}
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Days Before Check-in <span class="text-red-500">*</span></label>
        <div class="relative rounded-md shadow-sm">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <x-heroicon-o-calendar class="h-5 w-5 text-gray-400" />
            </div>
            <input type="number" wire:model.live="days_before" min="0" class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
        </div>
        @error('days_before') <p class="mt-1 text-xs text-red-600 block">{{ $message }}</p> @enderror
    </div>

    {{-- Is Refundable Dropdown --}}
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Is This Refundable? <span class="text-red-500">*</span></label>
        <select wire:model.live="is_refundable" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <option value="Yes, Refundable">{{ __('admin.yes_refundable') }}</option>
            <option value="Non - Refundable">{{ __('admin.non_refundable') }}</option>
        </select>
        @error('is_refundable') <p class="mt-1 text-xs text-red-600 block">{{ $message }}</p> @enderror
    </div>

    {{-- Refund Percentage --}}
    <div x-data="{ isRefundable: @entangle('is_refundable') }" x-show="isRefundable === 'Yes, Refundable'">
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Refund Percentage <span class="text-red-500">*</span></label>
        <div class="relative rounded-md shadow-sm">
            <input type="number" wire:model.live="refund_percent" min="0" max="100" class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <span class="text-gray-500 sm:text-sm dark:text-gray-400">%</span>
            </div>
        </div>
        @error('refund_percent') <p class="mt-1 text-xs text-red-600 block">{{ $message }}</p> @enderror
    </div>
</div>

{{-- Preview Banner --}}
<div class="mt-5 rounded-lg bg-blue-50 p-4 border border-blue-100 flex items-center gap-3 text-blue-800 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300">
    <x-heroicon-o-computer-desktop class="h-5 w-5 shrink-0" />
    <p class="text-sm font-medium">
        Preview: 
        @if(is_numeric($days_before))
            @if($is_refundable === 'Yes, Refundable')
                {{ $refund_percent ?? 0 }}% refund if cancelled {{ $days_before }} days before check-in.
            @else
                0% refund (Non-Refundable) if cancelled {{ $days_before }} days before check-in.
            @endif
        @else
            Select days before check-in to see preview.
        @endif
    </p>
</div>
