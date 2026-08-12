@props([
    'latPath',
    'lngPath',
    'token' => config('services.mapbox.token'),
    'searchPlaceholder' => __('Search for an address in Eindhoven…'),
    'searchLabel' => __('Search'),
    'hint' => __('Drag the pin or click the map to set the location.'),
    'outOfBoundsMessage' => __('Please choose a location within Eindhoven.'),
    'unavailableMessage' => __('The map cannot be loaded right now.'),
    'error' => null,
    'errorId' => 'location-error',
])

{{--
    Reusable Eindhoven location picker, shared by the Filament admin "Locatie"
    field and the public experience submission form. The map and address search
    are constrained to the Eindhoven municipality so a pin can only be dropped
    inside the city. Styling is self-contained so it renders identically in both
    the admin panel and the public site, regardless of their Tailwind builds.
--}}
@once
    <style>
        .location-picker{ display:flex; flex-direction:column; gap:8px; font-family:inherit; }
        .location-picker__search{ position:relative; display:flex; gap:8px; }
        .location-picker__input-wrap{ position:relative; flex:1; }
        .location-picker__input{ width:100%; box-sizing:border-box; border:1px solid rgba(0,0,0,.15); border-radius:8px; padding:9px 12px; font-size:14px; line-height:1.4; background:#fff; color:#1a1a1a; }
        .location-picker__input:focus{ outline:none; border-color:#FF6600; box-shadow:0 0 0 3px rgba(255,102,0,.18); }
        .location-picker__btn{ flex-shrink:0; border:none; cursor:pointer; border-radius:8px; padding:9px 16px; font-size:14px; font-weight:600; color:#fff; background:#FF6600; }
        .location-picker__btn:hover{ background:#D23430; }
        .location-picker__suggestions{ position:absolute; z-index:30; left:0; right:0; margin-top:4px; max-height:240px; overflow:auto; list-style:none; margin-bottom:0; padding:4px 0; background:#fff; border-radius:8px; box-shadow:0 10px 28px rgba(0,0,0,.16); border:1px solid rgba(0,0,0,.08); }
        .location-picker__suggestion{ cursor:pointer; padding:7px 12px; font-size:14px; color:#1a1a1a; }
        .location-picker__suggestion:hover{ background:#f3f4f6; }
        .location-picker__map{ width:100%; height:360px; border-radius:8px; overflow:hidden; border:1px solid rgba(0,0,0,.12); }
        .location-picker__hint{ font-size:12px; color:#6b7280; margin:0; }
        .location-picker__error{ font-size:13px; color:#D23430; font-weight:500; margin:0; }
        .location-picker__unavailable{ font-size:14px; color:#D23430; margin:0; }
        [x-cloak]{ display:none !important; }
    </style>

    <script>
        window.eindhovenLoadMapbox = function (callback) {
            if (window.mapboxgl) {
                callback();

                return;
            }

            if (! document.getElementById('mapbox-gl-css')) {
                const link = document.createElement('link');
                link.id = 'mapbox-gl-css';
                link.rel = 'stylesheet';
                link.href = 'https://api.mapbox.com/mapbox-gl-js/v3.24.0/mapbox-gl.css';
                document.head.appendChild(link);
            }

            let script = document.getElementById('mapbox-gl-js');

            if (! script) {
                script = document.createElement('script');
                script.id = 'mapbox-gl-js';
                script.src = 'https://api.mapbox.com/mapbox-gl-js/v3.24.0/mapbox-gl.js';
                document.head.appendChild(script);
            }

            script.addEventListener('load', callback);
        };

        window.eindhovenLocationMapField = function (config) {
            return {
                map: null,
                marker: null,
                address: '',
                token: config.token,
                latState: config.latState,
                lngState: config.lngState,
                defaultCenter: [5.4697, 51.4416],

                // Eindhoven municipality bounding box — the map cannot be panned
                // or zoomed outside of it and pins can only land inside the city.
                bounds: [
                    [5.38, 51.38], // SW
                    [5.57, 51.51], // NE
                ],

                outOfBoundsMessage: '',
                outOfBoundsLabel: config.outOfBoundsMessage ?? 'Please choose a location within Eindhoven.',

                suggestions: [],
                suggestionTimeout: null,

                // Entangled Livewire values can arrive as numbers, numeric strings,
                // empty strings, null or undefined. Coerce them to a finite number or null.
                toCoordinate(value) {
                    if (value === null || value === undefined || value === '') {
                        return null;
                    }

                    const number = Number(value);

                    return Number.isFinite(number) ? number : null;
                },

                get hasCoordinates() {
                    return this.toCoordinate(this.latState) !== null
                        && this.toCoordinate(this.lngState) !== null;
                },

                isWithinEindhoven(lng, lat) {
                    const [[west, south], [east, north]] = this.bounds;

                    return lng >= west && lng <= east && lat >= south && lat <= north;
                },

                init() {
                    if (! this.token) {
                        return;
                    }

                    window.eindhovenLoadMapbox(() => this.initMap());
                },

                initMap() {
                    window.mapboxgl.accessToken = this.token;

                    const hadCoordinates = this.hasCoordinates;
                    const lng = this.toCoordinate(this.lngState) ?? this.defaultCenter[0];
                    const lat = this.toCoordinate(this.latState) ?? this.defaultCenter[1];

                    this.map = new window.mapboxgl.Map({
                        container: this.$refs.map,
                        style: 'mapbox://styles/mapbox/streets-v12',
                        center: [lng, lat],
                        zoom: 13,
                        minZoom: 10.5,
                        maxBounds: this.bounds,
                    });

                    this.map.addControl(new window.mapboxgl.NavigationControl(), 'top-right');

                    this.marker = new window.mapboxgl.Marker({ draggable: true, color: '#FF6600' })
                        .setLngLat([lng, lat])
                        .addTo(this.map);

                    // If there were no coordinates yet, store the default so the
                    // pin still lands somewhere sensible inside Eindhoven.
                    if (! hadCoordinates) {
                        this.syncFromMarker();
                    }

                    this.marker.on('dragend', () => this.commitMarker());

                    this.map.on('click', (event) => {
                        this.placeMarker(event.lngLat.lng, event.lngLat.lat);
                    });
                },

                placeMarker(lng, lat) {
                    if (! this.isWithinEindhoven(lng, lat)) {
                        this.outOfBoundsMessage = this.outOfBoundsLabel;

                        return;
                    }

                    this.marker.setLngLat([lng, lat]);
                    this.commitMarker();
                },

                commitMarker() {
                    const { lng, lat } = this.marker.getLngLat();

                    if (! this.isWithinEindhoven(lng, lat)) {
                        this.outOfBoundsMessage = this.outOfBoundsLabel;

                        // Snap back to the last valid coordinates inside Eindhoven.
                        const validLng = this.toCoordinate(this.lngState) ?? this.defaultCenter[0];
                        const validLat = this.toCoordinate(this.latState) ?? this.defaultCenter[1];
                        this.marker.setLngLat([validLng, validLat]);

                        return;
                    }

                    this.outOfBoundsMessage = '';
                    this.syncFromMarker();
                },

                syncFromMarker() {
                    const { lng, lat } = this.marker.getLngLat();
                    this.latState = Number(lat.toFixed(6));
                    this.lngState = Number(lng.toFixed(6));
                },

                async geocode(query, options = {}) {
                    const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json`
                        + `?access_token=${this.token}`
                        + '&proximity=5.4697,51.4416'
                        + '&bbox=5.38,51.38,5.57,51.51'
                        + '&country=nl'
                        + `&limit=${options.limit ?? 1}`
                        + (options.autocomplete ? '&autocomplete=true' : '');

                    const response = await fetch(url);
                    const data = await response.json();

                    // Defensively drop any result that falls outside Eindhoven so
                    // suggestions only ever offer locations within the city.
                    return (data.features ?? []).filter((feature) => {
                        const [lng, lat] = feature.center;

                        return this.isWithinEindhoven(lng, lat);
                    });
                },

                onAddressInput() {
                    clearTimeout(this.suggestionTimeout);

                    if (! this.address || this.address.length < 3 || ! this.token) {
                        this.suggestions = [];

                        return;
                    }

                    this.suggestionTimeout = setTimeout(async () => {
                        try {
                            this.suggestions = await this.geocode(this.address, { autocomplete: true, limit: 5 });
                        } catch (error) {
                            this.suggestions = [];
                        }
                    }, 300);
                },

                selectSuggestion(feature) {
                    this.address = feature.place_name;
                    this.suggestions = [];

                    const [lng, lat] = feature.center;
                    this.placeMarker(lng, lat);

                    if (this.isWithinEindhoven(lng, lat)) {
                        this.map.flyTo({ center: [lng, lat], zoom: 15 });
                    }
                },

                async searchAddress() {
                    if (! this.address || ! this.token || ! this.map) {
                        return;
                    }

                    try {
                        const features = await this.geocode(this.address);

                        if (features.length) {
                            this.selectSuggestion(features[0]);
                        } else {
                            this.outOfBoundsMessage = this.outOfBoundsLabel;
                        }
                    } catch (error) {
                        // Ignore geocoding failures; the pin can still be dragged manually.
                    }
                },
            };
        };
    </script>
@endonce

@if (blank($token))
    <p class="location-picker__unavailable">{{ $unavailableMessage }}</p>
@else
    <div
        wire:ignore
        x-data="eindhovenLocationMapField({
            token: @js($token),
            latState: $wire.$entangle(@js($latPath), true),
            lngState: $wire.$entangle(@js($lngPath), true),
            outOfBoundsMessage: @js($outOfBoundsMessage),
        })"
        x-init="init()"
        class="location-picker"
    >
        <div class="location-picker__search">
            <div class="location-picker__input-wrap" @click.outside="suggestions = []">
                <input
                    type="text"
                    x-model="address"
                    @input="onAddressInput()"
                    @keydown.enter.prevent="searchAddress()"
                    @keydown.escape="suggestions = []"
                    placeholder="{{ $searchPlaceholder }}"
                    autocomplete="off"
                    class="location-picker__input"
                    @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                >

                <ul class="location-picker__suggestions" x-show="suggestions.length > 0" x-cloak>
                    <template x-for="suggestion in suggestions" :key="suggestion.id">
                        <li
                            @click="selectSuggestion(suggestion)"
                            x-text="suggestion.place_name"
                            class="location-picker__suggestion"
                        ></li>
                    </template>
                </ul>
            </div>

            <button type="button" @click="searchAddress()" class="location-picker__btn">
                {{ $searchLabel }}
            </button>
        </div>

        <div x-ref="map" class="location-picker__map"></div>

        @if (filled($hint))
            <p class="location-picker__hint" x-show="! outOfBoundsMessage">
                {{ $hint }}
                <span x-show="hasCoordinates">
                    (<span x-text="toCoordinate(latState)"></span>, <span x-text="toCoordinate(lngState)"></span>)
                </span>
            </p>
        @endif

        <p class="location-picker__error" x-show="outOfBoundsMessage" x-cloak x-text="outOfBoundsMessage"></p>
    </div>
@endif
