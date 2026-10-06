<x-filament-panels::page>
    @php
        $propertyType = $this->getPropertyType();
        $taxes = $this->getPropertyTypeTaxes();
        $propertyCount = $this->getPropertyCount();
    @endphp

    @if ($propertyType)
        <div class="property-type-card rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            style="max-width: 480px;">
            {{-- Icon --}}
            @if ($propertyType->icon_url)
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950">
                    <img src="{{ $propertyType->icon_url }}" alt="{{ $propertyType->name }}"
                        class="h-7 w-7 object-contain" />
                </div>
            @endif

            {{-- Name --}}
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ $propertyType->name }}
            </h3>

            {{-- Description --}}
            @if ($propertyType->description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $propertyType->description }}
                </p>
            @endif

            {{-- Tax Badges --}}
            @if ($taxes->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($taxes as $tax)
                        <span
                            class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            {{ $this->getTaxBadgeLabel($tax) }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Divider --}}
            <hr class="mt-4 border-gray-200 dark:border-gray-700" />

            {{-- Footer: Property Count + Edit Button --}}
            <div class="mt-3 flex items-center justify-between">
                <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-building-office class="h-4 w-4" />
                    {{ $propertyCount }} {{ $propertyCount > 1 ? __('admin.properties') : __('admin.property') }}
                </div>

                {{ $this->editPropertyTypeAction }}
            </div>
        </div>
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-building-office-2 class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.no_property_type') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_property_type_description') }}
                </p>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>