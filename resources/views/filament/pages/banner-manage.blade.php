<x-filament-panels::page>
    {{-- Tab Switcher --}}
    <div class="flex items-center justify-between">
        <div class="inline-flex gap-1 p-2 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
            <button
                wire:click="switchTab('global')"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $currentTab === 'global' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
            >
                <x-heroicon-o-globe-alt class="h-4 w-4" />
                {{ __('admin.global') }}
            </button>

            <button
                wire:click="switchTab('country')"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $currentTab === 'country' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
            >
                <x-heroicon-o-map-pin class="h-4 w-4" />
                {{ $this->getCountryTabLabel() }}
            </button>
        </div>

        @if ($currentTab === 'country')
            {{ $this->addCountryBannerAction }}
        @else
            {{ $this->addGlobalBannerAction }}
        @endif
    </div>

    {{-- Tab Content --}}
    @if ($this->getHasBanners())
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-flag class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    @if ($currentTab === 'global')
                        {{ __('admin.no_global_banners_yet') }}
                    @else
                        {{ __('admin.you_havent_added_any_banners_yet') }}
                    @endif
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    @if ($currentTab === 'global')
                        {{ __('admin.global_banners_are_shown_as_a_fallback_when_a_countrys_banners_arent_available') }}
                    @else
                        {{ __('admin.banners_are_displayed_as_sliders_on_the_app_and_web_home_screens_add_banners_to_control_the_images_shown_to_users') }}
                    @endif
                </p>
            </div>
        </div>

        <x-filament-actions::modals />
    @endif

    {{-- Banner Preview Modal --}}
    <div
        x-data="{ open: false, url: '' }"
        x-on:open-banner-preview.window="url = $event.detail.url; open = true"
        x-on:keydown.escape.window="open = false"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-6"
        x-on:click.self="open = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="relative max-h-[85vh] max-w-5xl">
            <button
                x-on:click="open = false"
                class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg transition hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
            <img
                :src="url"
                alt="Banner Preview"
                class="max-h-[85vh] w-auto rounded-lg shadow-2xl"
            />
        </div>
    </div>
</x-filament-panels::page>
