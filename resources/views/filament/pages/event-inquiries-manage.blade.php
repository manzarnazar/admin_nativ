<x-filament-panels::page>
    @if ($this->hasInquiries())
        {{ $this->table }}
    @else
        <div class="flex flex-col items-center justify-center py-20 bg-[#F9FAFB] dark:bg-gray-900/50 rounded-3xl border border-gray-200/50 dark:border-gray-800 mt-8">
            <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-md ring-1 ring-gray-950/5">
                <x-filament::icon
                    icon="heroicon-o-calendar-days"
                    class="h-7 w-7 text-blue-600"
                />
            </div>

            <h3 class="text-base font-bold text-gray-950 dark:text-white mb-2">
                {{ __('admin.no_event_inquiries_yet') }}
            </h3>

            <p class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-lg leading-relaxed px-4">
                {{ __('admin.no_event_inquiries_description') }}
            </p>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
