<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ─── Stat Cards ──────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Total Partners --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#E7F4FE] p-5 dark:bg-blue-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#2196F3]">
                    <x-heroicon-o-user-group class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.total_partners') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalPartners }}</p>
                </div>
            </div>

            {{-- Active Partners --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#E5FAEF] p-5 dark:bg-green-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#20B364]">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.active_partners') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $activePartners }}</p>
                </div>
            </div>

            {{-- Inactive Partners --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#FEF5E6] p-5 dark:bg-amber-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#F79E1B]">
                    <x-heroicon-o-clock class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.inactive_partners') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $inactivePartners }}</p>
                </div>
            </div>

            {{-- Suspended Partners --}}
            <div class="flex items-center gap-4 rounded-xl bg-[#FBEAEA] p-5 dark:bg-red-900/20">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#D63031]">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('admin.suspended_partners') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $suspendedPartners }}</p>
                </div>
            </div>

        </div>

        {{-- ─── Table ──────────────────────────────────────────────────────────── --}}
        {{ $this->table }}

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
