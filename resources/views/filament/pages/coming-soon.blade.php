<x-filament-panels::page>
    <div class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-primary-50 dark:bg-primary-950">
            <x-heroicon-o-wrench-screwdriver class="w-8 h-8 text-primary-600 dark:text-primary-400" />
        </div>
        <div class="text-center">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $title ?? __('admin.coming_soon') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $description ?? __('admin.coming_soon_description') }}
            </p>
        </div>
    </div>
</x-filament-panels::page>
