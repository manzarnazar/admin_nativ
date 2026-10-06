<div class="flex items-center gap-3 px-3 py-4">
    <span class="city-pin-avatar">
        <x-icon-map-pin-area class="h-6 w-6" />
    </span>

    <div class="flex flex-col">
        <span class="font-semibold text-gray-950 dark:text-white">{{ $record->name }}</span>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ strtoupper($record->country?->iso_code ?? '') }}</span>
    </div>
</div>
