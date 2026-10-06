<x-filament-panels::page>
    @php
        $room = $this->getRoom();
        $roomType = $room->roomType;
        $images = $roomType->images;
        $amenities = $roomType->facilities;
        $currency = $this->getCurrencySymbol();
        $taxAmount = $this->getTaxAmount();
        $basePrice = (float) $room->base_price_per_night;
        $totalAmount = $basePrice + $taxAmount;
        $reviews = $this->getReviews();
        $reviewsCount = $room->reviews_count;
        $averageRating = $room->reviews_avg_rating !== null ? round((float) $room->reviews_avg_rating, 1) : null;
    @endphp

    {{-- Back Link --}}
    <div class="-mt-4 mb-3">
        <a
            href="{{ \App\Filament\Pages\AllRoomsManage::getUrl() }}"
            wire:navigate
            class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_all_rooms') }}
        </a>
    </div>

    {{-- Header --}}
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-start gap-5">
            <div class="shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800" style="width: 80px; height: 80px;">
                @if ($images->isNotEmpty())
                    <img src="{{ asset('storage/' . $images->first()->image_path) }}" alt="{{ $roomType->name }}" style="width: 80px; height: 80px; object-fit: cover;" />
                @else
                    <div class="flex items-center justify-center" style="width: 80px; height: 80px;">
                        <x-heroicon-o-building-office class="h-10 w-10 text-gray-400" />
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-bold text-gray-950 dark:text-white">{{ $roomType->name }}</h1>
                <div class="mt-1 flex items-center justify-between">
                    <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                        ID - {{ str_pad((string) $room->id, 4, '0', STR_PAD_LEFT) }}
                    </span>
                    <div class="flex items-center gap-1.5 text-sm">
                        <x-heroicon-s-star class="h-4 w-4 text-yellow-400" />
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $averageRating !== null ? number_format($averageRating, 1) : '—' }}</span>
                        <span class="text-gray-500 dark:text-gray-400">({{ $reviewsCount }} {{ __('admin.reviews') }})</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Room Description --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.room_description') }}</h3>
            <div class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                {!! $roomType->description ?: '-' !!}
            </div>
        </div>

        {{-- Room Photos --}}
        @if ($images->isNotEmpty())
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.room_photos') }} ({{ $images->count() }})
                </h3>
                <div class="mt-4 grid grid-cols-4 gap-3">
                    @foreach ($images as $image)
                        <div class="aspect-[4/3] overflow-hidden rounded-lg">
                            <img src="{{ asset('storage/' . $image->image_path) }}" alt="{{ $roomType->name }}" class="h-full w-full object-cover transition hover:scale-105" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Room Specification --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.room_specification') }}</h3>
            <div class="mt-4 grid grid-cols-4 gap-4">
                <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <x-heroicon-o-users class="h-5 w-5 text-gray-400" />
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.max_guests') }}</span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $roomType->max_guests }}</span>
                </div>
                <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2 17.5V20h20v-2.5M2 17.5V15a2 2 0 012-2h4a2 2 0 012 2v2.5M2 17.5h8m12 0V10a2 2 0 00-2-2H10v9.5m12 0H10M4 13V8.5A1.5 1.5 0 015.5 7h3A1.5 1.5 0 0110 8.5V13" /></svg>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.bed_type') }}</span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $roomType->bed_type }}</span>
                </div>
                <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <x-heroicon-o-square-3-stack-3d class="h-5 w-5 text-gray-400" />
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.room_size') }}</span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $room->room_size ? $room->room_size.' sqft' : '-' }}</span>
                </div>
                <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <x-heroicon-o-home class="h-5 w-5 text-gray-400" />
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.total_rooms') }}</span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $room->total_rooms }}</span>
                </div>
            </div>
        </div>

        {{-- Room Amenities --}}
        @if ($amenities->isNotEmpty())
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.room_amenities') }}</h3>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    @foreach ($amenities as $amenity)
                        <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                            <x-heroicon-s-check-circle class="h-4 w-4 shrink-0 text-green-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $amenity->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Room Reviews --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('admin.room_reviews') }}@if ($reviewsCount > 0) <span class="text-gray-400">({{ $reviewsCount }})</span>@endif
            </h3>

            @if ($reviewsCount > 0)
                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    @foreach ($reviews as $review)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        src="{{ $review->user?->getFilamentAvatarUrl() ?: asset('avatars/defaultUser.svg') }}"
                                        alt="{{ $review->user?->name }}"
                                        class="h-9 w-9 shrink-0 rounded-full object-cover"
                                    />
                                    <div>
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $review->user?->name ?? '—' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $review->created_at->format('M d, Y') }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1 text-sm">
                                    <x-heroicon-s-star class="h-4 w-4 text-yellow-400" />
                                    <span class="font-semibold text-gray-950 dark:text-white">{{ number_format((float) $review->rating, 1) }}</span>
                                </div>
                            </div>
                            @if ($review->review)
                                <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ $review->review }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($reviews->hasPages())
                    <div class="mt-4">
                        {{ $reviews->links() }}
                    </div>
                @endif
            @else
                <div class="mt-4 flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-8 text-center dark:border-gray-700">
                    <x-heroicon-o-chat-bubble-left-right class="h-8 w-8 text-gray-400" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_reviews_yet') }}</p>
                </div>
            @endif
        </div>

        {{-- Pricing Details --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.pricing_details') }}</h3>

            <div class="mt-4 flex flex-col items-center rounded-lg bg-primary-50 px-6 py-5 dark:bg-primary-950/30">
                <span class="text-2xl font-bold text-primary-700 dark:text-primary-300">
                    {{ $currency }}{{ number_format($basePrice, 2) }}
                </span>
                <span class="text-sm text-primary-600 dark:text-primary-400">{{ __('admin.per_night') }}</span>
            </div>

            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('admin.base_price') }}</span>
                    <span class="font-medium text-gray-950 dark:text-white">{{ $currency }}{{ number_format($basePrice, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('admin.tax_and_fees') }}</span>
                    <span class="font-medium text-gray-950 dark:text-white">{{ $currency }}{{ number_format($taxAmount, 2) }}</span>
                </div>
                <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-gray-950 dark:text-white">{{ __('admin.total_amount') }}</span>
                        <span class="font-bold text-gray-950 dark:text-white">{{ $currency }}{{ number_format($totalAmount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
