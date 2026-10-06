@php
    $countryIso  = $this->getCurrentCountryIsoCode();
    $countryName = $this->getCurrentCountryName();
    $photonUrl = config('maps.photon_url');
@endphp

@if ($countryName)
    <div class="mb-3 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-700 dark:bg-blue-950">
        <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
        <p class="text-sm text-blue-800 dark:text-blue-200">
            {!! __('admin.property_address_country_note', ['country' => e($countryName)]) !!}
        </p>
    </div>
@endif

<div
    wire:ignore
    x-data="{
        lat: $wire.$entangle('data.latitude'),
        lng: $wire.$entangle('data.longitude'),
        streetAddress: $wire.$entangle('data.street_address'),
        zipCode: $wire.$entangle('data.zip_code'),
        countryIso: '{{ $countryIso }}',
        photonUrl: '{{ $photonUrl }}',
        defaultZoom: {{ config('maps.default_zoom', 13) }},
        map: null,
        marker: null,
        debounceTimer: null,
        suggestions: [],
        showSuggestions: false,
        searchQuery: '',
        isLoading: false,

        init() {
            this.$nextTick(() => this.setupMap());
        },

        setupMap() {
            if (typeof L !== 'undefined') {
                this.initLeaflet();
                return;
            }

            if (!document.querySelector('link[href*=leaflet]')) {
                const css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(css);
            }

            if (!document.querySelector('script[src*=leaflet]')) {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.onload = () => this.initLeaflet();
                document.head.appendChild(script);
            } else {
                const interval = setInterval(() => {
                    if (typeof L !== 'undefined') {
                        clearInterval(interval);
                        this.initLeaflet();
                    }
                }, 100);
            }
        },

        initLeaflet() {
            const defaultLat = this.lat || 20.5937;
            const defaultLng = this.lng || 78.9629;
            const hasCoords  = !!(this.lat && this.lng);

            this.map = L.map(this.$refs.mapContainer, { zoomControl: true }).setView(
                [parseFloat(defaultLat), parseFloat(defaultLng)],
                hasCoords ? this.defaultZoom : 5
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a> contributors',
                maxZoom: 19,
            }).addTo(this.map);

            if (hasCoords) {
                this.marker = L.marker([parseFloat(this.lat), parseFloat(this.lng)], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.lat  = pos.lat.toFixed(8);
                    this.lng  = pos.lng.toFixed(8);
                });
            }

            this.map.on('click', (e) => {
                this.lat = e.latlng.lat.toFixed(8);
                this.lng = e.latlng.lng.toFixed(8);
                this.placeMarker(e.latlng.lat, e.latlng.lng);
            });

            this.$watch('lat', () => this.syncMarkerFromCoords());
            this.$watch('lng', () => this.syncMarkerFromCoords());
        },

        placeMarker(lat, lng) {
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
            } else {
                this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.lat  = pos.lat.toFixed(8);
                    this.lng  = pos.lng.toFixed(8);
                });
            }
            this.map.setView([lat, lng], this.defaultZoom);
        },

        syncMarkerFromCoords() {
            if (!this.lat || !this.lng || !this.map) return;
            const lat = parseFloat(this.lat);
            const lng = parseFloat(this.lng);
            if (isNaN(lat) || isNaN(lng)) return;
            this.placeMarker(lat, lng);
        },

        onSearchInput() {
            clearTimeout(this.debounceTimer);
            if (this.searchQuery.length < 3) {
                this.suggestions    = [];
                this.showSuggestions = false;
                return;
            }
            this.debounceTimer = setTimeout(() => this.fetchSuggestions(), 500);
        },

        async fetchSuggestions() {
            this.isLoading = true;
            const params = new URLSearchParams({ q: this.searchQuery, limit: '10', lang: 'en' });
            if (this.countryIso) { params.set('countrycode', this.countryIso.toLowerCase()); }

            // Prefer the pinned location as bias; fall back to map view centre.
            let biasLat = parseFloat(this.lat);
            let biasLng = parseFloat(this.lng);
            if ((isNaN(biasLat) || isNaN(biasLng)) && this.map) {
                const centre = this.map.getCenter();
                biasLat = centre.lat;
                biasLng = centre.lng;
            }
            if (!isNaN(biasLat) && !isNaN(biasLng)) {
                params.set('lat', biasLat.toString());
                params.set('lon', biasLng.toString());
                params.set('location_bias_scale', '0.5');
            }

            try {
                const res  = await fetch(this.photonUrl + '/api?' + params.toString());
                const data = await res.json();
                this.suggestions = (data.features || []).map(f => {
                    const p = f.properties;
                    const parts = [
                        p.name,
                        p.street,
                        p.district || p.suburb,
                        p.city || p.town || p.village || p.county,
                        p.state,
                    ].filter(Boolean);
                    return { ...f, _label: [...new Set(parts)].join(', '), _id: (p.osm_id || '') + '_' + (p.osm_type || '') };
                });
                this.showSuggestions = this.suggestions.length > 0;
            } catch (e) {
                this.suggestions = [];
            } finally {
                this.isLoading = false;
            }
        },

        selectSuggestion(item) {
            this.searchQuery     = item._label;
            this.showSuggestions = false;
            this.suggestions     = [];

            // Photon returns GeoJSON: coordinates are [lng, lat]
            const lng = item.geometry.coordinates[0];
            const lat = item.geometry.coordinates[1];
            this.lat  = lat.toFixed(8);
            this.lng  = lng.toFixed(8);
            this.placeMarker(lat, lng);

            const p = item.properties;
            this.zipCode = p.postcode || '';

            const streetParts = [p.housenumber, p.street, p.district].filter(Boolean);
            this.streetAddress = streetParts.length
                ? streetParts.join(', ')
                : p.name || '';

            const stateName = p.state || '';
            const cityName  = p.city || p.county || '';
            if (stateName) { $wire.setStateAndCityFromPlace(stateName, cityName); }
        },
    }"
    class="relative isolate space-y-3"
    @click.outside="showSuggestions = false"
>
    {{-- Search input — must exceed both Leaflet's map panes (max 700) AND its controls
         container (.leaflet-top/.leaflet-bottom, zoom buttons + attribution), which
         Leaflet's own CSS sets to 1000 independently of the panes. The wrapper's
         `isolate` keeps this whole component in its own stacking context so these
         numbers only compete with each other, never with page chrome like the topbar. --}}
    <div class="relative" style="z-index: 1100;">
        <div class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
            <x-heroicon-o-magnifying-glass class="h-4 w-4 flex-shrink-0 text-gray-400" />
            <input
                type="text"
                x-model="searchQuery"
                @input="onSearchInput"
                @keydown.escape="showSuggestions = false"
                placeholder="{{ __('admin.search_address') }}"
                class="w-full bg-transparent text-sm text-gray-900 placeholder-gray-400 outline-none dark:text-white dark:placeholder-gray-500"
            />
            <x-heroicon-o-arrow-path x-show="isLoading" class="h-4 w-4 animate-spin flex-shrink-0 text-gray-400" />
        </div>

        {{-- Suggestions dropdown --}}
        <div
            x-show="showSuggestions"
            x-transition
            class="absolute mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
            style="z-index: 1001;"
        >
            <template x-for="item in suggestions" :key="item._id">
                <button
                    type="button"
                    @click="selectSuggestion(item)"
                    class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <x-heroicon-o-map-pin class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" />
                    <span x-text="item._label" class="line-clamp-2"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Map container --}}
    <div
        x-ref="mapContainer"
        style="height: 300px; min-height: 300px;"
        class="w-full overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600"
    ></div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('admin.osm_map_tip') }}
    </p>
</div>
