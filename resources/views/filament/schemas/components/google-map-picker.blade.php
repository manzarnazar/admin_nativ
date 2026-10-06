@php
    $apiKey = $this->getGoogleMapsApiKey();
    $countryIso = $this->getCurrentCountryIsoCode();
    $countryName = $this->getCurrentCountryName();
@endphp

@if (! $apiKey)
    <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-600 dark:bg-yellow-950">
        <div class="flex items-center gap-2">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
            <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                {{ __('admin.google_maps_api_key_not_configured') }}
            </p>
        </div>
    </div>
@else
    @if ($countryName)
        <div class="mb-3 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-700 dark:bg-blue-950">
            <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
            <p class="text-sm text-blue-800 dark:text-blue-200">
                {!! __('admin.property_address_country_note', ['country' => e($countryName)]) !!}
            </p>
        </div>
    @endif

    {{-- PlaceAutocompleteElement has its own search icon inside its Shadow DOM,
         so we don't render our own. The host element is forced transparent so
         the inner input drives the visible look. --}}
    <style>
        .map-picker-search gmp-place-autocomplete {
            display: block !important;
            width: 100% !important;
            background-color: rgb(255 255 255) !important;
            border: 1px solid rgb(209 213 219) !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
            outline: none !important;
            transition: border-color 0.15s, box-shadow 0.15s;
            color-scheme: light;
            --gmpx-color-surface: #ffffff;
            --gmpx-color-on-surface: #111827;
            --gmpx-color-on-surface-variant: #6b7280;
            --gmpx-color-primary: #3b82f6;
        }
        .map-picker-search gmp-place-autocomplete:focus-within {
            border-color: rgb(59 130 246) !important;
            box-shadow: 0 0 0 1px rgb(59 130 246) inset, 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
        }
        .map-picker-search gmp-place-autocomplete::part(input) {
            width: 100%;
            box-sizing: border-box;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: rgb(17 24 39);
        }
        .map-picker-search gmp-place-autocomplete::part(input):focus {
            outline: none;
        }
        .dark .map-picker-search gmp-place-autocomplete {
            color-scheme: dark;
            background-color: rgb(31 41 55) !important;
            border-color: rgb(75 85 99) !important;
            --gmpx-color-surface: #1f2937;
            --gmpx-color-on-surface: #ffffff;
        }
        .dark .map-picker-search gmp-place-autocomplete::part(input) {
            color: rgb(255 255 255);
        }
    </style>

    <div
        wire:ignore
        x-data="{
            lat: $wire.$entangle('data.latitude'),
            lng: $wire.$entangle('data.longitude'),
            streetAddress: $wire.$entangle('data.street_address'),
            zipCode: $wire.$entangle('data.zip_code'),
            countryIso: '{{ $countryIso }}',
            defaultZoom: {{ config('maps.default_zoom', 13) }},
            map: null,
            marker: null,
            autocomplete: null,

            init() {
                this.$nextTick(() => this.setupMapWithScriptCheck());
            },

            setupMapWithScriptCheck() {
                if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                    if (!document.querySelector('script[src*=\'maps.googleapis.com\']')) {
                        const script = document.createElement('script');
                        script.src = 'https://maps.googleapis.com/maps/api/js?key={{ $apiKey }}&libraries=places&callback=Function.prototype';
                        script.async = true;
                        script.defer = true;
                        script.onload = () => this.setupMap();
                        document.head.appendChild(script);
                    } else {
                        const interval = setInterval(() => {
                            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                                clearInterval(interval);
                                this.setupMap();
                            }
                        }, 100);
                    }
                    return;
                }
                this.setupMap();
            },

            setupMap() {
                const defaultLat = this.lat || 20.5937;
                const defaultLng = this.lng || 78.9629;
                const hasCoords = !!(this.lat && this.lng);

                this.map = new google.maps.Map(this.$refs.mapContainer, {
                    center: { lat: parseFloat(defaultLat), lng: parseFloat(defaultLng) },
                    zoom: hasCoords ? this.defaultZoom : 5,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                });

                this.marker = new google.maps.Marker({
                    map: this.map,
                    draggable: true,
                    position: hasCoords ? { lat: parseFloat(defaultLat), lng: parseFloat(defaultLng) } : null,
                    visible: hasCoords,
                });

                this.marker.addListener('dragend', (event) => {
                    this.lat = event.latLng.lat().toFixed(8);
                    this.lng = event.latLng.lng().toFixed(8);
                });

                this.autocomplete = new google.maps.places.PlaceAutocompleteElement();
                if (this.countryIso) {
                    this.autocomplete.includedRegionCodes = [this.countryIso];
                }
                // Inline backstop: guarantees the host has dimensions even if external CSS fails to apply
                this.autocomplete.style.display = 'block';
                this.autocomplete.style.width = '100%';

                this.$refs.searchContainer.replaceChildren(this.autocomplete);

                this.autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
                    const place = placePrediction.toPlace();
                    await place.fetchFields({
                        fields: ['formattedAddress', 'location', 'addressComponents'],
                    });

                    if (!place.location) return;

                    this.map.setCenter(place.location);
                    this.map.setZoom(this.defaultZoom);
                    this.marker.setPosition(place.location);
                    this.marker.setVisible(true);

                    this.lat = place.location.lat().toFixed(8);
                    this.lng = place.location.lng().toFixed(8);

                    if (place.addressComponents) {
                        // Types that belong on the street/building line — everything more
                        // granular than the city, so City/State/Zip aren't repeated inside it.
                        const streetTypes = ['subpremise', 'premise', 'street_number', 'route', 'neighborhood', 'sublocality_level_2', 'sublocality_level_1'];

                        let stateName = '';
                        let cityName = '';
                        let cityComponent = null;

                        for (const component of place.addressComponents) {
                            if (component.types.includes('administrative_area_level_1')) {
                                stateName = component.longText;
                            }
                            if (component.types.includes('locality')) {
                                cityName = component.longText;
                                cityComponent = component;
                            } else if (!cityName && component.types.includes('administrative_area_level_2')) {
                                cityName = component.longText;
                                cityComponent = component;
                            } else if (!cityName && component.types.includes('sublocality_level_1')) {
                                cityName = component.longText;
                                cityComponent = component;
                            }
                        }

                        const streetParts = [];
                        for (const component of place.addressComponents) {
                            if (component.types.includes('postal_code')) {
                                this.zipCode = component.longText;
                            }
                            if (component === cityComponent) {
                                continue;
                            }
                            if (streetTypes.some((type) => component.types.includes(type))) {
                                streetParts.push(component.longText);
                            }
                        }

                        // Fall back to the full formatted address on the rare place that has
                        // no street-level components at all (e.g. picking a whole city/region).
                        this.streetAddress = streetParts.length ? streetParts.join(', ') : (place.formattedAddress || '');

                        if (stateName) {
                            $wire.setStateAndCityFromPlace(stateName, cityName);
                        }
                    }
                });

                this.$watch('lat', () => this.updateMarkerFromCoords());
                this.$watch('lng', () => this.updateMarkerFromCoords());
            },

            updateMarkerFromCoords() {
                if (!this.lat || !this.lng || !this.map || !this.marker) return;
                const position = { lat: parseFloat(this.lat), lng: parseFloat(this.lng) };
                if (isNaN(position.lat) || isNaN(position.lng)) return;
                this.marker.setPosition(position);
                this.marker.setVisible(true);
                this.map.setCenter(position);
                this.map.setZoom(15);
            },
        }"
        class="space-y-3"
    >
        {{-- Search Input (PlaceAutocompleteElement gets mounted into searchContainer at runtime; it includes its own search icon) --}}
        <div class="map-picker-search">
            <div x-ref="searchContainer" style="display: block; width: 100%; min-height: 2.5rem;"></div>
        </div>

        {{-- Map Container --}}
        <div
            x-ref="mapContainer"
            style="height: 300px; min-height: 300px;"
            class="w-full overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600"
        ></div>
    </div>
@endif
