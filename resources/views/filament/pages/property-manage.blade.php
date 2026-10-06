<x-filament-panels::page>
    @if (\App\Support\SystemMode::isMulti())
        @php $stats = $this->getPropertyStats(); @endphp
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            {{-- Total Properties --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#E7F4FE] p-5 dark:bg-blue-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#2196F3]">
                    <x-heroicon-o-building-office-2 class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.total_properties') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                </div>
            </div>

            {{-- Active Properties --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#E5FAEF] p-5 dark:bg-green-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#20B364]">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.active_properties') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['active'] }}</p>
                </div>
            </div>

            {{-- Inactive Properties --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#FEF5E6] p-5 dark:bg-amber-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#F79E1B]">
                    <x-heroicon-o-x-circle class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.inactive_properties') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['inactive'] }}</p>
                </div>
            </div>

            {{-- Suspended Properties --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#FBEAEA] p-5 dark:bg-red-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#D63031]">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.suspended_properties') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['suspended'] }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($this->hasProperties())
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-building-office class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.no_properties_added') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_properties_description') }}
                </p>
            </div>
        </div>
    @endif
</x-filament-panels::page>
