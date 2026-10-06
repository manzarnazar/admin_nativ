<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ─── Operating Countries ───────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.operating_countries') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.operating_countries_description') }}</p>
                </div>

                @if ($hasAvailableCountries)
                    {{ $this->addCountryAction }}
                @endif
            </div>

            {{-- Countries list --}}
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($countries as $country)
                    <div class="flex items-center gap-x-4 px-6 py-4">
                        {{-- Flag / icon --}}
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary-50 text-xl dark:bg-primary-900/20">
                            @if ($country->refCountry?->emoji)
                                {{ $country->refCountry->emoji }}
                            @else
                                <x-heroicon-o-globe-alt class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                            @endif
                        </div>

                        {{-- Name + ISO --}}
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $country->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $country->iso_code }}{{ $country->phone_code ? ' · +' . $country->phone_code : '' }}</p>
                        </div>

                        {{-- Currency --}}
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $country->currency_code }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $country->currency_name }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center">
                        <x-heroicon-o-globe-alt class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_countries_added_yet') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
