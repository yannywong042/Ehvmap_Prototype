import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import './submit-photo';

window.mapboxgl = mapboxgl;

// Fallback for browsers without dvh support: expose the real visible viewport
// height (excluding mobile browser UI chrome) as the --vh custom property the
// .site--map rule reads. Modern browsers use 100dvh and ignore this entirely.
const setViewportHeightUnit = () => {
    document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
};

setViewportHeightUnit();
window.addEventListener('resize', setViewportHeightUnit);
window.addEventListener('orientationchange', setViewportHeightUnit);

const CLUSTER_SOURCE_ID = 'em-experiences';
const CLUSTER_LAYER_ID = `${CLUSTER_SOURCE_ID}-loader`;
const CLUSTER_MAX_ZOOM = 16;

window.miniMapPreview = () => ({
    map: null,

    initMap(points) {
        mapboxgl.accessToken = document.querySelector('meta[name="mapbox-token"]')?.content ?? '';

        this.map = new mapboxgl.Map({
            container: this.$refs.canvas,
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [5.4697, 51.4416],
            zoom: 12.2,
            interactive: false,
            attributionControl: false,
        });

        this.map.on('load', () => {
            points.forEach((point) => {
                const el = document.createElement('div');
                el.className = 'em-pin';
                el.style.pointerEvents = 'none';
                el.innerHTML = `
                    <svg viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:32px;height:42px">
                        <path d="M16 2C8.27 2 2 8.27 2 16c0 4.2 2.2 8.4 5.3 12.1C10.4 31.8 13.8 34.8 16 36.5c2.2-1.7 5.6-4.7 8.7-8.4C27.8 24.4 30 20.2 30 16 30 8.27 23.73 2 16 2z" fill="#FF6600"/>
                        <circle cx="16" cy="16" r="5" fill="white"/>
                    </svg>`;

                new mapboxgl.Marker({ element: el })
                    .setLngLat([point.longitude, point.latitude])
                    .addTo(this.map);
            });
        });
    },
});

window.mapExplorer = () => ({
    map: null,
    markers: [],
    selectedId: null,
    selectedExperience: null,
    detailMode: null, // null | 'full'
    filtersOpen: false, // mobile floating filter dropdown
    currentExperiences: [],
    visibleExperiences: [], // currentExperiences currently within the map viewport
    mapLoaded: false,
    mapError: '',
    neighbourhoods: [],
    selectedNeighbourhood: null,

    initMap(experiences, neighbourhoods = [], selectedNeighbourhood = null) {
        mapboxgl.accessToken = document.querySelector('meta[name="mapbox-token"]')?.content ?? '';
        this.currentExperiences = experiences;
        this.neighbourhoods = neighbourhoods ?? [];
        this.selectedNeighbourhood = selectedNeighbourhood ?? null;
        // Seed the bottom POI carousel immediately so exploring works even before
        // (or without) a successful Mapbox load; refined to the viewport on moveend.
        this.visibleExperiences = this.currentExperiences.filter(
            (e) => e.longitude !== null && e.latitude !== null
        );

        // Eindhoven municipality bounding box — prevents panning outside the city
        const eindhovenBounds = [
            [5.38, 51.38], // SW — Eindhoven municipality edge
            [5.57, 51.51], // NE — Eindhoven municipality edge
        ];

        this.map = new mapboxgl.Map({
            container: 'mapbox-map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [5.4697, 51.4416],
            zoom: 12,
            minZoom: 10.5,
            maxBounds: eindhovenBounds,
            // Keep the map north-up: disable rotation and tilt entirely.
            bearing: 0,
            pitch: 0,
            dragRotate: false,
            pitchWithRotate: false,
            touchPitch: false,
        });

        // Keep pinch-to-zoom working, but disable two-finger rotation.
        this.map.touchZoomRotate.disableRotation();

        // Keep arrow-key pan and +/- zoom, but disable Shift+Arrow rotate/pitch.
        this.map.keyboard.disableRotation();

        this.map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

        this.map.on('load', () => {
            this.mapLoaded = true;
            this.mapError = '';
            this.addClusterSource();
            this.syncMarkers(this.currentExperiences);
            this.renderNeighbourhoodBoundaries();

            if (this.selectedNeighbourhood) {
                this.flyToNeighbourhood(this.selectedNeighbourhood);
            }

            this.updateVisibleExperiences();
        });

        this.map.on('error', () => {
            if (!this.mapLoaded) {
                this.mapError =
                    this.$el?.dataset.mapLoadError ??
                    'Unable to load the map right now. Please try again.';
                // Keep carousel populated while the map is unavailable.
                this.visibleExperiences = this.currentExperiences.filter(
                    (e) => e.longitude !== null && e.latitude !== null
                );
            }
        });

        // Recompute clusters as the viewport changes so pins merge/split smoothly
        this.map.on('moveend', () => {
            this.renderMarkers();
            this.updateVisibleExperiences();
        });

        // Re-sync markers when Livewire updates the list
        window.addEventListener('livewire:navigated', () => {
            this.syncMarkers(this.currentExperiences);
            this.updateVisibleExperiences();
        });

        this.$wire.on('experiences-updated', ({ experiences }) => {
            this.currentExperiences = experiences;
            this.syncMarkers(experiences);
            this.updateVisibleExperiences();
        });
    },

    addClusterSource() {
        if (this.map.getSource(CLUSTER_SOURCE_ID)) {
            return;
        }

        this.map.addSource(CLUSTER_SOURCE_ID, {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: [] },
            cluster: true,
            clusterMaxZoom: CLUSTER_MAX_ZOOM,
            clusterRadius: 80,
        });

        // Source tiles only load once a layer forces them to — and that same
        // layer lets us query rendered (viewport-bound) features in renderMarkers()
        // instead of querySourceFeatures(), which would include off-screen tiles.
        // Pins/clusters themselves are rendered as DOM markers, not via this layer.
        this.map.addLayer({
            id: CLUSTER_LAYER_ID,
            type: 'circle',
            source: CLUSTER_SOURCE_ID,
            paint: { 'circle-radius': 0, 'circle-opacity': 0 },
        });
    },

    renderNeighbourhoodBoundaries() {
        if (!this.map || this.neighbourhoods.length === 0) {
            return;
        }

        const featureCollection = {
            type: 'FeatureCollection',
            features: this.neighbourhoods
                .filter((n) => n.boundary)
                .map((n) => ({
                    type: 'Feature',
                    properties: { slug: n.slug, name: n.name },
                    geometry: n.boundary,
                })),
        };

        if (this.map.getSource('neighbourhood-boundaries')) {
            this.map.getSource('neighbourhood-boundaries').setData(featureCollection);
            return;
        }

        this.map.addSource('neighbourhood-boundaries', {
            type: 'geojson',
            data: featureCollection,
        });

        this.map.addLayer({
            id: 'neighbourhood-boundaries-line',
            type: 'line',
            source: 'neighbourhood-boundaries',
            paint: {
                'line-color': '#FF6600',
                'line-width': ['case', ['==', ['get', 'slug'], this.selectedNeighbourhood?.slug ?? ''], 2.5, 1],
                'line-opacity': ['case', ['==', ['get', 'slug'], this.selectedNeighbourhood?.slug ?? ''], 0.9, 0.35],
            },
        });
    },

    onNeighbourhoodSelected(neighbourhood) {
        this.selectedNeighbourhood = neighbourhood ?? null;

        if (this.map?.getLayer('neighbourhood-boundaries-line')) {
            this.map.setPaintProperty('neighbourhood-boundaries-line', 'line-width', [
                'case',
                ['==', ['get', 'slug'], this.selectedNeighbourhood?.slug ?? ''],
                2.5,
                1,
            ]);
            this.map.setPaintProperty('neighbourhood-boundaries-line', 'line-opacity', [
                'case',
                ['==', ['get', 'slug'], this.selectedNeighbourhood?.slug ?? ''],
                0.9,
                0.35,
            ]);
        }

        if (this.selectedNeighbourhood) {
            this.flyToNeighbourhood(this.selectedNeighbourhood);
        } else {
            this.map?.flyTo({ center: [5.4697, 51.4416], zoom: 12 });
        }
    },

    flyToNeighbourhood(neighbourhood) {
        if (!this.map || !neighbourhood?.center) {
            return;
        }

        this.map.flyTo({ center: [neighbourhood.center.lng, neighbourhood.center.lat], zoom: 14 });
    },

    syncMarkers(exps) {
        if (!this.map) {
            return;
        }

        const source = this.map.getSource(CLUSTER_SOURCE_ID);

        if (!source) {
            return;
        }

        this.currentExperiences = exps;

        source.setData({
            type: 'FeatureCollection',
            features: exps
                .filter((e) => e.latitude !== null && e.longitude !== null)
                .map((e) => ({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [e.longitude, e.latitude] },
                    properties: { experienceId: e.id },
                })),
        });

        // GeoJSON source updates are async; wait a tick before reading clusters back.
        // Coalesce repeated syncMarkers() calls into a single pending render so a
        // stale snapshot can't flash in if syncMarkers fires again before idle.
        if (this.pendingRender) {
            return;
        }

        this.pendingRender = true;
        this.map.once('idle', () => {
            this.pendingRender = false;
            this.renderMarkers();
        });
    },

    renderMarkers() {
        if (!this.map || !this.map.getSource(CLUSTER_SOURCE_ID)) {
            return;
        }

        this.markers.forEach((m) => m.remove());
        this.markers = [];

        const experiencesById = new Map(this.currentExperiences.map((e) => [e.id, e]));
        const features = this.map.queryRenderedFeatures(undefined, { layers: [CLUSTER_LAYER_ID] });
        const seenClusterIds = new Set();
        const seenExperienceIds = new Set();

        features.forEach((feature) => {
            const [longitude, latitude] = feature.geometry.coordinates;
            const { cluster, cluster_id: clusterId, point_count: pointCount } = feature.properties;

            if (cluster) {
                if (seenClusterIds.has(clusterId)) {
                    return;
                }

                seenClusterIds.add(clusterId);
                this.addClusterMarker(clusterId, pointCount, [longitude, latitude]);

                return;
            }

            const experience = experiencesById.get(feature.properties.experienceId);

            if (!experience || seenExperienceIds.has(experience.id)) {
                return;
            }

            seenExperienceIds.add(experience.id);
            this.addPinMarker(experience);
        });
    },

    addClusterMarker(clusterId, pointCount, lngLat) {
        const sizeClass = pointCount >= 50 ? ' em-cluster--xl' : pointCount >= 10 ? ' em-cluster--lg' : '';

        const el = document.createElement('div');
        el.className = `em-cluster${sizeClass}`;
        el.innerHTML = `<span class="em-cluster__count">${pointCount}</span>`;
        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '0');
        el.setAttribute('aria-label', `Zoom in to ${pointCount} experiences`);
        el.addEventListener('click', () => this.zoomToCluster(clusterId, pointCount));
        el.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.zoomToCluster(clusterId, pointCount);
            }
        });

        const marker = new mapboxgl.Marker({ element: el }).setLngLat(lngLat).addTo(this.map);

        this.markers.push(marker);
    },

    zoomToCluster(clusterId, pointCount) {
        const source = this.map.getSource(CLUSTER_SOURCE_ID);

        // Fit to the cluster's actual leaves rather than just its expansion
        // zoom, so every pin in the cluster stays on screen after zooming.
        // Limit is the cluster's own point_count, so the fetch is bounded
        // and never over-fetches regardless of cluster size.
        source.getClusterLeaves(clusterId, pointCount, 0, (error, leaves) => {
            if (error || !leaves.length) {
                return;
            }

            const bounds = leaves.reduce(
                (b, leaf) => b.extend(leaf.geometry.coordinates),
                new mapboxgl.LngLatBounds(leaves[0].geometry.coordinates, leaves[0].geometry.coordinates),
            );

            this.map.fitBounds(bounds, { padding: this.mapPadding(), maxZoom: 18 });
        });
    },

    mapPadding() {
        const padding = { top: 80, bottom: 80, left: 80, right: 80 };

        // The bottom carousel covers this much of the map whenever it's showing.
        if (this.visibleExperiences.length > 0 && this.detailMode !== 'full') {
            padding.bottom = 220;
        }

        return padding;
    },

    addPinMarker(e) {
        const el = document.createElement('div');
        el.className = 'em-pin' + (this.selectedId === e.id ? ' is-selected' : '');
        el.innerHTML = `
            <svg viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:32px;height:42px;cursor:pointer">
                <path d="M16 2C8.27 2 2 8.27 2 16c0 4.2 2.2 8.4 5.3 12.1C10.4 31.8 13.8 34.8 16 36.5c2.2-1.7 5.6-4.7 8.7-8.4C27.8 24.4 30 20.2 30 16 30 8.27 23.73 2 16 2z" fill="${this.selectedId === e.id ? '#D23430' : '#FF6600'}"/>
                <circle cx="16" cy="16" r="5" fill="white"/>
            </svg>`;
        el.addEventListener('click', () => this.selectExperience(e));

        const marker = new mapboxgl.Marker({ element: el })
            .setLngLat([e.longitude, e.latitude])
            .addTo(this.map);

        this.markers.push(marker);
    },

    // Flag emoji for a story's source language, used by the "translated" pill.
    // Mirrors the locales defined in components/em/site-header.blade.php.
    localeFlag(locale) {
        return { en: '🇬🇧', nl: '🇳🇱' }[locale] ?? '';
    },

    updateVisibleExperiences() {
        if (!this.map) {
            this.visibleExperiences = [];

            return;
        }

        const bounds = this.map.getBounds();

        this.visibleExperiences = this.currentExperiences.filter(
            (e) => e.longitude !== null && e.latitude !== null && bounds.contains([e.longitude, e.latitude])
        );
    },

    selectExperience(experienceOrId) {
        const experience =
            typeof experienceOrId === 'object'
                ? experienceOrId
                : this.currentExperiences.find((exp) => exp.id === experienceOrId);

        if (!experience) {
            return;
        }

        if (this.selectedId === experience.id) {
            this.toggleDetail();

            return;
        }

        this.selectedId = experience.id;
        this.selectedExperience = experience;
        this.detailMode = null;
        this.filtersOpen = false;

        if (experience.longitude !== null && experience.latitude !== null) {
            this.map.flyTo({
                center: [experience.longitude, experience.latitude],
                // Past clusterMaxZoom so the selected pin always renders as
                // its own marker instead of disappearing back into a cluster.
                zoom: Math.max(this.map.getZoom(), CLUSTER_MAX_ZOOM + 1),
                padding: this.mapPadding(),
            });
        }

        this.syncMarkers(this.currentExperiences);

        this.$nextTick(() => {
            this.$el
                .querySelectorAll(`[data-experience-id="${experience.id}"]`)
                .forEach((item) => item.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }));
        });
    },

    openFullDetail() {
        this.detailMode = 'full';
    },

    closeDetail() {
        this.detailMode = null;
    },

    toggleDetail() {
        if (this.detailMode === 'full') {
            this.closeDetail();
        } else {
            this.openFullDetail();
        }
    },

    handleEscape() {
        if (this.filtersOpen) {
            this.filtersOpen = false;
        } else if (this.detailMode === 'full') {
            this.closeDetail();
        }
    },

    toggleFilters() {
        this.filtersOpen = !this.filtersOpen;
    },

    showNoExperiencesMessage() {
        return this.mapLoaded && this.currentExperiences.length === 0 && !this.mapError;
    },
});
