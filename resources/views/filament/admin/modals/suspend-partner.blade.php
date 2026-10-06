@props(['partner'])

<div class="space-y-4">
    {{-- Warning Box --}}
    <div class="flex items-start gap-4 rounded-xl bg-[#FBEAEA] p-4 dark:bg-red-900/20">
        <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-[#D63031]">
            <x-heroicon-o-exclamation-triangle class="h-7 w-7 text-white" />
        </div>
        <div>
            <h4 class="font-bold text-gray-900 dark:text-white">Warning: Are you sure you want to suspend this partner?</h4>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                Suspending this partner will also suspend all associated properties.<br>
                You can reactivate the partner at any time.
            </p>
        </div>
    </div>

    {{-- Partner Info Box --}}
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-3">
            @if($partner->user?->avatar)
                <img src="{{ Storage::disk('public')->url($partner->user->avatar) }}" alt="{{ $partner->user->name }}" class="h-12 w-12 rounded-xl object-cover">
            @else
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                    <x-heroicon-o-user class="h-6 w-6" />
                </div>
            @endif
            <div>
                <p class="font-bold text-gray-900 dark:text-white">{{ $partner->user?->name ?? 'Unknown' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ trim(($partner->refAddressCity?->name ?? '') . ' · ' . ($partner->refAddressState?->name ?? ''), ' ·') ?: 'Unknown Location' }}
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-sm font-semibold text-primary-600 dark:text-primary-400">Partner ID</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">#{{ str_pad($partner->id, 2, '0', STR_PAD_LEFT) }}</p>
        </div>
    </div>
</div>
