<x-filament-panels::page>
    {{-- Tab Switcher --}}
    <div class="flex">
    <div class="inline-flex gap-1 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
        <button
            wire:click="switchTab('default_rates')"
            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
            style="{{ $currentTab === 'default_rates' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
        >
            <x-heroicon-o-percent-badge class="h-4 w-4" />
            {{ __('admin.default_rates') }}
        </button>

        <button
            wire:click="switchTab('partner_overrides')"
            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
            style="{{ $currentTab === 'partner_overrides' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
        >
            <x-heroicon-o-user-group class="h-4 w-4" />
            {{ __('admin.partner_overrides') }}
        </button>
    </div>
    </div>

    {{-- Default Rates Tab --}}
    @if ($currentTab === 'default_rates')
        @php
            $defaultRate = $this->getDefaultRate();
            $propertyTypes = $this->getPropertyTypesWithRates();
        @endphp

        {{-- Default Commission Rate Banner --}}
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/50">
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('admin.default_commission_rate') }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.default_commission_rate_description') }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if ($defaultRate !== null)
                    <span class="text-xl font-bold text-gray-900 dark:text-white">{{ $defaultRate }}%</span>
                @else
                    <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('admin.not_set') }}</span>
                @endif
                <button
                    wire:click="mountAction('editDefaultRate')"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                >
                    <x-phosphor-pencil-simple-line class="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Property Type Cards --}}
        @if ($propertyTypes->isNotEmpty())
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($propertyTypes as $type)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {{-- Header: icon + name + badge --}}
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                @if ($type['icon_url'])
                                    <img src="{{ $type['icon_url'] }}" alt="{{ $type['name'] }}" class="h-6 w-6 object-contain" />
                                @else
                                    <x-heroicon-o-building-office class="h-5 w-5 text-gray-400" />
                                @endif
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $type['name'] }}</span>
                            </div>
                            @if ($type['is_overridden'])
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" style="background-color: #dbeafe; color: #1d4ed8;">
                                    {{ __('admin.overridden') }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    {{ __('admin.default') }}
                                </span>
                            @endif
                        </div>

                        {{-- Rate + Actions --}}
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ $type['rate'] !== null ? $type['rate'] . '%' : '—' }}
                            </span>
                            <div class="flex items-center gap-1">
                                <button
                                    wire:click="mountAction('editTypeRate', {{ json_encode(['propertyTypeId' => $type['id']]) }})"
                                    class="fi-icon-btn fi-color-gray fi-size-md fi-icon-btn-size-md relative flex items-center justify-center rounded-lg outline-none transition duration-75 fi-icon-btn-icon-gray-400"
                                >
                                    <x-phosphor-pencil-simple-line class="h-5 w-5 text-gray-400 hover:text-gray-600" />
                                </button>
                                @if ($type['is_overridden'])
                                    <button
                                        wire:click="mountAction('deleteTypeRate', {{ json_encode(['propertyTypeId' => $type['id']]) }})"
                                        class="fi-icon-btn fi-color-danger fi-size-md fi-icon-btn-size-md relative flex items-center justify-center rounded-lg outline-none transition duration-75"
                                    >
                                        <x-phosphor-trash class="h-5 w-5 text-red-500 hover:text-red-600" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center rounded-xl border border-gray-200 bg-white px-6 py-12 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-percent-badge class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>
                <h4 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.no_property_types') }}</h4>
                <p class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_property_types_for_commission') }}</p>
            </div>
        @endif

    {{-- Partner Overrides Tab --}}
    @else
        @livewire('commission-partner-override-table', key('commission-partner-override-table'))
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
