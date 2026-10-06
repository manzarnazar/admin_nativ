@php
    $nearbyPlaces = $this->getExistingNearbyPlaces();
    $allPendingPlaces = $this->pendingPlaces;
    $searchResults = $this->nearbySearchResults;
    $searchCategoryId = $this->nearbySearchCategoryId;
    $searchQuery = $this->nearbySearchQuery;
@endphp

<div class="space-y-3" x-data="{ openCategory: null }">
    {{-- Section Header --}}
    <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('admin.nearby_places') }}
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('admin.search_and_add_nearby_places_for_this_city') }}
            </p>
        </div>
    </div>

    @if (! $hasApiKey)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0 text-amber-500" />
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('admin.google_maps_api_key_not_configured') }}
                </p>
            </div>
        </div>
    @endif

    @forelse ($categories as $category)
        @php
            $categoryPlaces = $nearbyPlaces[$category->id] ?? collect();
            $categoryPending = $allPendingPlaces[$category->id] ?? [];
            $totalCount = $categoryPlaces->count() + count($categoryPending);
            $isSearchingThisCategory = $searchCategoryId === $category->id;
        @endphp

        <div class="rounded-lg border border-gray-200 dark:border-gray-700">
            {{-- Accordion Header --}}
            <button
                type="button"
                class="flex w-full items-center justify-between p-3 text-left"
                x-on:click="openCategory = openCategory === {{ $category->id }} ? null : {{ $category->id }}"
            >
                <div class="flex items-center gap-3">
                    @if ($category->icon)
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->icon) }}"
                            class="h-6 w-6 object-contain"
                            alt="{{ $category->name }}"
                        >
                    @else
                        <x-heroicon-o-map-pin class="h-5 w-5 text-gray-400" />
                    @endif
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $category->name }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        {{ $totalCount }}
                    </span>
                </div>
                <x-heroicon-o-chevron-down
                    class="h-4 w-4 text-gray-400 transition-transform duration-200"
                    x-bind:class="openCategory === {{ $category->id }} ? 'rotate-180' : ''"
                />
            </button>

            {{-- Accordion Content --}}
            <div
                x-show="openCategory === {{ $category->id }}"
                x-collapse
                class="border-t border-gray-200 dark:border-gray-700"
            >
                <div class="space-y-3 p-3">
                    {{-- Search Input --}}
                    <div class="relative" x-data="{ debounceTimer: null }">
                        <input
                            type="text"
                            x-on:input.debounce.600ms="$wire.searchNearbyPlaces({{ $category->id }}, $event.target.value)"
                            placeholder="{{ __('admin.search_places_placeholder') }}"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                        <div
                            wire:loading
                            wire:target="searchNearbyPlaces"
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2"
                        >
                            <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin text-gray-400" />
                        </div>
                    </div>

                    {{-- Search Results --}}
                    @if ($isSearchingThisCategory && count($searchResults) > 0)
                        <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-primary-200 bg-primary-50 p-2 dark:border-primary-800 dark:bg-primary-900/20">
                            <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('admin.search_results') }} ({{ count($searchResults) }})
                            </p>
                            @foreach ($searchResults as $index => $result)
                                <div class="flex items-center justify-between rounded-md bg-white p-2 dark:bg-gray-800">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $result['name'] }}
                                        </p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $result['address'] }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="addNearbyPlace({{ $category->id }}, {{ $index }})"
                                        wire:loading.attr="disabled"
                                        class="ml-2 shrink-0 rounded-md bg-primary-600 px-2 py-1 text-xs font-medium text-white hover:bg-primary-500 disabled:opacity-50"
                                    >
                                        + {{ __('admin.add') }}
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($isSearchingThisCategory && count($searchResults) === 0 && $searchQuery !== '')
                        <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                            {{ __('admin.no_results_found') }}
                        </p>
                    @endif

                    {{-- Existing Places (from DB) --}}
                    @if ($categoryPlaces->isNotEmpty() || ! empty($categoryPending))
                        <div class="space-y-1">
                            @php $counter = 1; @endphp

                            {{-- Saved places --}}
                            @foreach ($categoryPlaces as $place)
                                <div class="flex items-center justify-between rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                            {{ $counter++ }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $place->name }}
                                            </p>
                                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                @if ($place->distance_km !== null)
                                                    <span>{{ $place->distance_km }} km</span>
                                                @endif
                                                @if ($place->rating !== null)
                                                    <span class="flex items-center gap-0.5">
                                                        <x-heroicon-s-star class="h-3 w-3 text-yellow-400" />
                                                        {{ $place->rating }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="markPlaceForRemoval({{ $place->id }})"
                                        class="ml-2 shrink-0 rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                    >
                                        <x-phosphor-trash class="h-4 w-4" />
                                    </button>
                                </div>
                            @endforeach

                            {{-- Pending places (not yet saved) --}}
                            @foreach ($categoryPending as $pendingIndex => $pending)
                                <div class="flex items-center justify-between rounded-lg border border-dashed border-green-300 bg-green-50 p-2.5 dark:border-green-700 dark:bg-green-900/20">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-200 text-xs font-medium text-green-700 dark:bg-green-800 dark:text-green-300">
                                            {{ $counter++ }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $pending['name'] }}
                                                <span class="ml-1 text-xs font-normal text-green-600 dark:text-green-400">{{ __('admin.new') }}</span>
                                            </p>
                                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                @if (! empty($pending['distance_km']))
                                                    <span>{{ $pending['distance_km'] }} km</span>
                                                @endif
                                                @if (! empty($pending['rating']))
                                                    <span class="flex items-center gap-0.5">
                                                        <x-heroicon-s-star class="h-3 w-3 text-yellow-400" />
                                                        {{ $pending['rating'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removePendingPlace({{ $category->id }}, {{ $pendingIndex }})"
                                        class="ml-2 shrink-0 rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                    >
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="py-2 text-center text-xs text-gray-400 dark:text-gray-500">
                            {{ __('admin.no_places_added_yet') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-gray-200 p-4 text-center dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('admin.no_categories_available_create_categories_first') }}
            </p>
        </div>
    @endforelse
</div>
