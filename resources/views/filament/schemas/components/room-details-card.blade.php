@php
    $roomType = $roomType ?? null;
    $amenitiesCount = $amenitiesCount ?? 0;
@endphp

@if ($roomType)
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-3 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('admin.room_details') }}</p>

        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950">
                <x-heroicon-o-building-office class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $roomType->name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <svg class="inline h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2 17.5V20h20v-2.5M2 17.5V15a2 2 0 012-2h4a2 2 0 012 2v2.5M2 17.5h8m12 0V10a2 2 0 00-2-2H10v9.5m12 0H10M4 13V8.5A1.5 1.5 0 015.5 7h3A1.5 1.5 0 0110 8.5V13" /></svg>
                    {{ $roomType->bed_type }}
                </p>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3">
            <div class="flex items-center gap-2 text-xs">
                <span class="font-medium text-gray-500 dark:text-gray-400">{{ __('admin.amenities') }}</span>
                <span class="flex items-center gap-1 font-semibold text-green-600 dark:text-green-400">
                    <x-heroicon-s-check-circle class="h-3.5 w-3.5" />
                    {{ $amenitiesCount }} {{ __('admin.selected') }}
                </span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="font-medium text-gray-500 dark:text-gray-400">{{ __('admin.max_guests') }}</span>
                <span class="flex items-center gap-1 font-semibold text-green-600 dark:text-green-400">
                    <x-heroicon-s-check-circle class="h-3.5 w-3.5" />
                    {{ $roomType->max_guests }} {{ __('admin.guests_allowed') }}
                </span>
            </div>
        </div>
    </div>
@endif
