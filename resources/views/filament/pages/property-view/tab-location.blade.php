@php
    $nearbyPlaces = $this->getNearbyPlaces();
    $hasCoords = $property->latitude && $property->longitude;
@endphp

<div x-data="{
    defaultMap: 'https://www.google.com/maps?q={{ $property->latitude }},{{ $property->longitude }}&z=15&output=embed',
    mapSrc: 'https://www.google.com/maps?q={{ $property->latitude }},{{ $property->longitude }}&z=15&output=embed',
    activePlaceId: null,
    activeDirectionsUrl: '',
    activePlaceName: '',
    mapType: 'm',
    isLoading: true
}" class="space-y-6" id="property-location-map">
    <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.location_and_nearby') }}</h3>
        <button 
            x-show="mapSrc !== defaultMap"
            @click="isLoading = true; mapSrc = defaultMap; activePlaceId = null; activeDirectionsUrl = ''; activePlaceName = ''"
            class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
            style="display: none;"
        >
            {{ __('admin.reset_map') ?? 'Reset Map' }}
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-5 sm:gap-6">
        {{-- Map --}}
        <div class="col-span-3">
            @if ($hasCoords)
                <div class="relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div x-show="isLoading" class="absolute inset-0 z-20 flex items-center justify-center bg-gray-50/50 backdrop-blur-sm transition-all duration-300 dark:bg-gray-900/50">
                        <x-filament::loading-indicator class="h-8 w-8 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="absolute right-3 top-3 z-10 flex overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800">
                        <button 
                            @click="if (mapType !== 'm') { isLoading = true; mapType = 'm' }" 
                            class="px-3 py-1.5 text-xs font-medium transition"
                            :class="mapType === 'm' ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200'"
                        >{{ __('admin.map') ?? 'Map' }}</button>
                        <button 
                            @click="if (mapType !== 'k') { isLoading = true; mapType = 'k' }" 
                            class="border-l border-gray-200 px-3 py-1.5 text-xs font-medium transition dark:border-gray-600"
                            :class="mapType === 'k' ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200'"
                        >{{ __('admin.satellite') ?? 'Satellite' }}</button>
                    </div>
                    <iframe
                        @load="isLoading = false"
                        width="100%"
                        height="350"
                        style="border:0"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        :src="mapSrc + '&t=' + mapType"
                    ></iframe>
                    <div class="flex items-center justify-between bg-gray-50 px-4 py-2 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <span>Lat: {{ $property->latitude }} &bull; Long: {{ $property->longitude }}</span>
                        <a
                            x-show="!activeDirectionsUrl"
                            href="https://www.google.com/maps?q={{ $property->latitude }},{{ $property->longitude }}"
                            target="_blank"
                            class="inline-flex items-center gap-1 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400"
                        >
                            <x-heroicon-o-map-pin class="h-3 w-3" />
                            {{ __('admin.open_on_google_maps') ?? 'Open on Google Maps' }}
                        </a>
                        <a 
                            x-show="activeDirectionsUrl"
                            :href="activeDirectionsUrl"
                            target="_blank"
                            class="inline-flex items-center gap-1 font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                            style="display: none;"
                        >
                            <span x-text="'{{ __('admin.get_directions') ?? 'Get Directions' }} to ' + activePlaceName"></span>
                            <x-heroicon-o-arrow-top-right-on-square class="h-3 w-3" />
                        </a>
                    </div>
                </div>
            @else
                <div class="flex h-[350px] items-center justify-center rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-400">{{ __('admin.no_coordinates_set') }}</p>
                </div>
            @endif
        </div>

        {{-- Property Address --}}
        <div class="col-span-2">
            <div class="rounded-lg bg-gray-50 p-5 dark:bg-gray-800">
                <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                    <x-heroicon-o-map-pin class="h-4 w-4" />
                    {{ __('admin.property_address') }}
                </h4>
                <div class="mt-4 space-y-3 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('admin.street_address') }}</span>
                        <p class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $property->street_address ?: '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('admin.country') }}</span>
                        <p class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $property->country?->name ?: '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('admin.state') }}</span>
                        <p class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $property->refState?->name ?: '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('admin.city') }}</span>
                        <p class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $property->refCity?->name ?: '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('admin.zip_code') }}</span>
                        <p class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $property->zip_code ?: '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Nearby Locations --}}
    @if ($nearbyPlaces->isNotEmpty())
        <div>
            <h4 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.nearby_locations') }}</h4>
            <div class="space-y-2">
                @foreach ($nearbyPlaces as $place)
                    @php
                        $destinationUrl = "https://www.google.com/maps?q={$place->latitude},{$place->longitude}&z=15&output=embed";
                        $directionsUrl = "https://maps.google.com/maps?saddr={$property->latitude},{$property->longitude}&daddr={$place->latitude},{$place->longitude}";
                    @endphp
                    <a 
                        href="#" 
                        @click.prevent="isLoading = true; mapSrc = '{{ $destinationUrl }}'; activePlaceId = {{ $place->id }}; activeDirectionsUrl = '{{ $directionsUrl }}'; activePlaceName = '{{ addslashes($place->name) }}'; document.getElementById('property-location-map').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="flex items-center justify-between rounded-lg px-5 py-3 transition"
                        :class="activePlaceId === {{ $place->id }} ? 'bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-900/40 dark:ring-primary-400' : 'bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700'"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950">
                                <x-heroicon-o-map-pin class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $place->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $place->nearbyPlaceCategory?->name ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                {{ $place->distance_km }} KM
                            </span>
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
