{{-- Shared stat cards below the Rooms & Pricing table. Expects: $stats (array{room_types:int, total_rooms:int}) --}}
<div class="mt-6 rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
    <div class="flex gap-7">
        <div class="flex-1 rounded-2xl bg-[var(--brand-primary-light)] p-4 text-center dark:bg-blue-900/20">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.total_room_type') }}</p>
            <p class="text-2xl font-semibold text-primary-600 dark:text-primary-400">{{ $stats['room_types'] }}</p>
        </div>
        <div class="flex-1 rounded-2xl bg-[#E5FAEF] p-4 text-center dark:bg-green-900/20">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.total_rooms') }}</p>
            <p class="text-2xl font-semibold text-[#20B364]">{{ $stats['total_rooms'] }}</p>
        </div>
    </div>
</div>
