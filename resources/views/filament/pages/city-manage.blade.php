<x-filament-panels::page>
    {{-- Tab Switcher --}}
    <div class="flex items-center justify-between">
    <div class="inline-flex gap-1 p-2 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
        <button
            wire:click="switchTab('cities')"
            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
            style="{{ $currentTab === 'cities' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
        >
            <x-heroicon-o-map-pin class="h-4 w-4" />
            {{ __('admin.cities') }}
        </button>

        <button
            wire:click="switchTab('nearby_categories')"
            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
            style="{{ $currentTab === 'nearby_categories' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
        >
            <x-heroicon-o-tag class="h-4 w-4" />
            {{ __('admin.nearby_categories') }}
        </button>
    </div>

        @if ($currentTab === 'cities')
            {{ $this->addNewCityAction }}
        @endif
    </div>

    {{-- Tab Content --}}
    @if ($currentTab === 'cities')
        @if ($this->hasCities())
            {{ $this->table }}
        @else
            <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-heroicon-o-map-pin class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                    </div>

                    <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('admin.no_cities_added_yet') }}
                    </h4>
                    <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                        {{ __('admin.cities_are_used_to_help_customers_search_and_discover_properties_on_the_website_add_cities_to_improve_locationbased_search_results_and_user_experience') }}
                    </p>
                </div>
            </div>
        @endif
    @else
        @livewire('nearby-place-category-table', key('nearby-place-category-table'))
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
