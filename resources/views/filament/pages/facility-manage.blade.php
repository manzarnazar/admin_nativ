<x-filament-panels::page>
    @if ($this->getHasCategories())
    {{-- Search Bar --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="px-5 py-4">
            <div class="relative max-w-md">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-400" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('admin.search_facilities_placeholder') }}"
                    class="block w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500 dark:focus:border-primary-500" />
            </div>
        </div>
    </div>

    {{-- Category Groups --}}
    <div class="space-y-5">
        @forelse ($this->getCategories() as $category)
        <div
            class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            {{-- Category Header --}}
            <div class="fac-cat-header flex flex-wrap items-center gap-3 px-5 py-4">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    @if ($category->getIconUrl())
                    <img src="{{ $category->getIconUrl() }}" alt="{{ $category->name }}"
                        class="h-7 w-7 shrink-0 object-contain" />
                    @endif

                    <div class="min-w-0">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $category->name }}
                        </span>
                        <span
                            class="ml-2 inline-flex rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                            {{ $category->facilities_count }} {{ $category->facilities_count > 1 ? __('admin.items') : __('admin.item') }}
                        </span>
                    </div>
                </div>

                <div class="fac-cat-actions flex shrink-0 items-center gap-2">
                    {{ ($this->addFacilityAction)(['category' => $category->id]) }}
                    {{ ($this->editCategoryAction)(['category' => $category->id]) }}
                    {{ ($this->deleteCategoryAction)(['category' => $category->id]) }}
                </div>
            </div>

            {{-- Facilities Grid --}}
            @if ($category->facilities->isNotEmpty())
            <div class="fac-grid px-5 py-5">
                @foreach ($category->facilities as $facility)
                @php $isActive = $facility->status === \App\Enums\FacilityStatus::Active; @endphp
                <div
                    class="facility-card group relative flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 transition hover:shadow-sm dark:border-gray-700"
                    style="{{ $isActive ? 'background-color: #ffffff;' : 'background-color: #EDEDED;' }}">

                    @if ($facility->getIconUrl())
                    <div class="shrink-0 rounded-lg p-1.5"
                        style="{{ $isActive ? 'background-color: var(--brand-primary-light);' : 'background-color: #D9D9D9;' }}">
                        <img src="{{ $facility->getIconUrl() }}" alt="{{ $facility->name }}"
                            class="h-5 w-5 object-contain"
                            style="{{ $isActive
                                ? 'filter: none;'
                                : 'filter: brightness(0) saturate(100%) invert(60%);' }}" />
                    </div>
                    @endif

                    <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300">
                        {{ $facility->name }}
                    </span>

                    <div
                        class="facility-card-actions absolute right-2 top-1/2 -translate-y-1/2 items-center gap-4 mr-4 px-2 py-1 space-between">
                        {{ ($this->editFacilityAction)(['facility' => $facility->id]) }}
                        {{ ($this->deleteFacilityAction)(['facility' => $facility->id]) }}
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @empty
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-magnifying-glass class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.no_results_found') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_facilities_match_your_search') }}
                </p>
            </div>
        </div>
        @endforelse
    </div>
    @else
    <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <x-heroicon-o-building-office class="h-6 w-6 text-gray-400 dark:text-gray-500" />
            </div>

            <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('admin.no_facilities_or_amenities_added') }}
            </h4>
            <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                {{ __('admin.create_facilities_and_amenities_to_standardize_property_features_and_improve_filtering') }}
            </p>
        </div>
    </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>