<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->getSettingsCards() as $card)
            <a href="{{ $card['url'] }}"
               wire:navigate
               class="group flex flex-col items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 text-center transition hover:border-primary-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 text-primary-600 transition group-hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $card['label'] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
