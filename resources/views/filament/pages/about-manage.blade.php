<x-filament-panels::page>
    @if (!\App\Support\SystemMode::isMulti())
    {{-- Accordion sections --}}
    <div class="space-y-4" x-data="{ openSection: 'key_highlights' }">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- WHO WE ARE SECTION --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

            {{-- Section Header --}}
            <div
                class="flex cursor-pointer items-center gap-4 px-5 py-4"
                x-on:click="openSection = openSection === 'who_we_are' ? null : 'who_we_are'"
            >
                {{-- Icon --}}
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 transition-colors duration-200 dark:bg-gray-800"
                    x-bind:style="openSection === 'who_we_are' ? 'background-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-user
                        class="h-5 w-5 text-gray-400 transition-colors duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'who_we_are' ? '!text-white' : ''"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.who_we_are_section') }}</h4>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.update_the_content_that_introduces_your_property_and_its_identity_to_guests') }}
                    </p>
                </div>

                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
                    x-bind:style="openSection === 'who_we_are' ? 'background-color: var(--brand-btn); border-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-chevron-down
                        class="h-4 w-4 text-gray-500 transition-transform duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'who_we_are' ? 'rotate-180 !text-white' : ''"
                    />
                </div>
            </div>

            {{-- Form Content --}}
            <div x-show="openSection === 'who_we_are'" x-collapse>
                <div class="border-t border-gray-200 px-5 py-6 dark:border-gray-700">
                    {{ $this->whoWeAreForm }}

                    <div class="mt-4">
                        <x-filament::button wire:click="saveWhoWeAre" size="sm">
                            {{ __('admin.save_changes') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- MANAGE KEY HIGHLIGHTS SECTION --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            {{-- Header --}}
            <div class="flex cursor-pointer items-center gap-4 px-5 py-4" x-on:click="openSection = openSection === 'key_highlights' ? null : 'key_highlights'">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 transition-colors duration-200 dark:bg-gray-800" x-bind:style="openSection === 'key_highlights' ? 'background-color: var(--brand-btn);' : ''">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-gray-400 transition-colors duration-200 dark:text-gray-400" x-bind:class="openSection === 'key_highlights' ? '!text-white' : ''" />
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.manage_key_highlights') }}</h4>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.add_up_to_4_key_points_to_showcase_your_propertys_strongest_features') }}</p>
                </div>
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800" x-bind:style="openSection === 'key_highlights' ? 'background-color: var(--brand-btn); border-color: var(--brand-btn);' : ''">
                    <x-heroicon-o-chevron-down class="h-4 w-4 text-gray-500 transition-transform duration-200 dark:text-gray-400" x-bind:class="openSection === 'key_highlights' ? 'rotate-180 !text-white' : ''" />
                </div>
            </div>

            {{-- Body --}}
            <div x-show="openSection === 'key_highlights'" x-collapse>
                <div class="border-t border-gray-200 px-5 py-6 dark:border-gray-700">
                    {{ $this->highlightsForm }}

                    <div class="mt-4">
                        <x-filament::button wire:click="saveKeyHighlights" size="sm">
                            {{ __('admin.save_changes') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- OUR PROMISE SECTION --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            {{-- Header --}}
            <div class="flex cursor-pointer items-center gap-4 px-5 py-4" x-on:click="openSection = openSection === 'our_promise' ? null : 'our_promise'">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 transition-colors duration-200 dark:bg-gray-800" x-bind:style="openSection === 'our_promise' ? 'background-color: var(--brand-btn);' : ''">
                    <x-heroicon-o-bars-3-bottom-left class="h-5 w-5 text-gray-500 transition-colors duration-200 dark:text-gray-400" x-bind:class="openSection === 'our_promise' ? '!text-white' : ''" />
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.our_promise_section') }}</h4>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.accepted_forms_of_payment_for_bookings_and_services') }}</p>
                </div>
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800" x-bind:style="openSection === 'our_promise' ? 'background-color: var(--brand-btn); border-color: var(--brand-btn);' : ''">
                    <x-heroicon-o-chevron-down class="h-4 w-4 text-gray-500 transition-transform duration-200 dark:text-gray-400" x-bind:class="openSection === 'our_promise' ? 'rotate-180 !text-white' : ''" />
                </div>
            </div>

            {{-- Body --}}
            <div x-show="openSection === 'our_promise'" x-collapse>
                <div class="border-t border-gray-200 px-5 py-6 dark:border-gray-700">
                    {{ $this->promiseForm }}

                    <div class="mt-4">
                        <x-filament::button wire:click="saveOurPromise" size="sm">
                            {{ __('admin.save_changes') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    @else
    {{-- Multi Mode Section --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 p-6">
        {{ $this->multiModeForm }}

        <div class="mt-4">
            <x-filament::button wire:click="saveMultiModeContent" size="sm">
                {{ __('admin.save_changes') }}
            </x-filament::button>
        </div>
    </div>
    @endif
</x-filament-panels::page>
