<div
    class="explorer"
    x-data="mapExplorer()"
    data-map-load-error="{{ __('map.map_load_error') }}"
    x-init="initMap(@js($this->experiencesForMap()), @js($this->neighbourhoods->map(fn ($n) => $n->toArray())->values()->all()), @js($this->selectedNeighbourhoodModel()?->toArray()))"
    @neighbourhood-selected.window="onNeighbourhoodSelected($event.detail.neighbourhood)"
    @keydown.escape.window="handleEscape()"
>
    <x-em.category-icons-sprite />

    {{-- Left panel: filters + list (hidden on phone — the map's horizontal carousel takes over) --}}
    <aside class="explorer__panel">
        <div class="explorer__search">
            <div class="em-input">
                <span class="em-input__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('map.search_placeholder') }}"
                    aria-label="{{ __('map.search_placeholder') }}"
                >
            </div>
        </div>

        <div class="explorer__neighbourhood">
            @if ($neighbourhoodsUnavailable)
                <p class="em-field__err">{{ __('map.neighbourhoods_unavailable') }}</p>
            @else
                @php
                    $selectedNeighbourhoodModel = $this->selectedNeighbourhoodModel();
                    $neighbourhoodLabel = $selectedNeighbourhoodModel?->displayName() ?? __('map.all_neighbourhoods');
                @endphp
                <div
                    class="em-buurt em-buurt--panel"
                    x-data="{ open: false }"
                    :class="{ 'em-buurt--open': open }"
                    @keydown.escape.window="if (open) open = false"
                    @click.outside="open = false"
                >
                    <button
                        type="button"
                        class="em-buurt__trigger"
                        @click="open = ! open"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-haspopup="listbox"
                        aria-label="{{ __('map.neighbourhood_label') }}"
                    >
                        <span class="em-buurt__pin" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <span class="em-buurt__labels">
                            <span class="em-buurt__eyebrow">{{ __('map.neighbourhood_label') }}</span>
                            <span class="em-buurt__name">{{ $neighbourhoodLabel }}</span>
                        </span>
                        <span class="em-buurt__chev" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </button>

                    <ul
                        class="em-buurt__menu"
                        role="listbox"
                        x-show="open"
                        x-cloak
                        x-transition.opacity.duration.120ms
                    >
                        <li>
                            <button
                                type="button"
                                role="option"
                                class="em-buurt__opt {{ ! $selectedNeighbourhood ? 'em-buurt__opt--active' : '' }}"
                                @if (! $selectedNeighbourhood) aria-selected="true" @endif
                                wire:click="selectNeighbourhood(null)"
                                @click="open = false"
                            >
                                <span class="em-buurt__check" aria-hidden="true">
                                    @if (! $selectedNeighbourhood)
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    @endif
                                </span>
                                {{ __('map.all_neighbourhoods') }}
                            </button>
                        </li>
                        @foreach ($this->neighbourhoods as $neighbourhood)
                            <li>
                                <button
                                    type="button"
                                    role="option"
                                    class="em-buurt__opt {{ $selectedNeighbourhood === $neighbourhood->slug() ? 'em-buurt__opt--active' : '' }}"
                                    @if ($selectedNeighbourhood === $neighbourhood->slug()) aria-selected="true" @endif
                                    wire:click="selectNeighbourhood('{{ $neighbourhood->slug() }}')"
                                    @click="open = false"
                                >
                                    <span class="em-buurt__check" aria-hidden="true">
                                        @if ($selectedNeighbourhood === $neighbourhood->slug())
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                        @endif
                                    </span>
                                    {{ $neighbourhood->displayName() }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="explorer__filters">
            @include('components.map-explorer.category-filters', ['categories' => $this->categories, 'activeCategories' => $activeCategories])
        </div>

        <div class="explorer__count">
            <span class="eyebrow">{{ trans_choice('map.result_count', $this->experiences->count(), ['count' => $this->experiences->count()]) }}</span>
            @if ($search || $activeCategories || $neighborhood || $selectedNeighbourhood)
                <button type="button" wire:click="clearFilters" class="em-tag em-tag--selectable">
                    {{ __('map.clear_filters') }} <span aria-hidden="true">&times;</span>
                </button>
            @endif
        </div>

        <div class="explorer__list" wire:loading.class="is-loading">
            @forelse ($this->experiences as $experience)
                <div
                    class="explorer__item"
                    data-experience-id="{{ $experience->id }}"
                    x-on:click="selectExperience({{ $experience->id }}); openFullDetail()"
                    :class="{ 'is-selected': selectedId === {{ $experience->id }} }"
                    wire:key="exp-{{ $experience->id }}"
                >
                    <x-em.experience-card :experience="$experience" />
                </div>
            @empty
                <p class="explorer__empty">{{ $selectedNeighbourhood ? __('map.no_results_in_neighbourhood') : __('map.no_results') }}</p>
            @endforelse
        </div>
    </aside>

    {{-- Right panel: Mapbox map --}}
    <div class="explorer__map">
        <div class="emap">
            <div id="mapbox-map" class="emap__canvas" wire:ignore></div>
            <p class="explorer__empty" x-show="showNoExperiencesMessage()" x-cloak>
                {{ $selectedNeighbourhood ? __('map.no_results_in_neighbourhood') : __('map.no_results') }}
            </p>
            <p class="explorer__empty" x-show="mapError" x-cloak x-text="mapError"></p>
        </div>

        {{-- Phone-only floating bar: search + filter toggle, overlaid on the map (Greenwheels-style) --}}
        <div class="emap-topbar">
            <div class="em-input emap-topbar__search">
                <span class="em-input__icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('map.search_placeholder') }}"
                    aria-label="{{ __('map.search_placeholder') }}"
                >
            </div>
            <button
                type="button"
                class="emap-topbar__btn"
                :class="{ 'is-active': filtersOpen }"
                @click="toggleFilters()"
                aria-label="{{ __('map.toggle_filters') }}"
                :aria-expanded="filtersOpen ? 'true' : 'false'"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="6" y2="6"/><circle cx="10" cy="6" r="2" fill="currentColor" stroke="none"/><line x1="4" x2="20" y1="12" y2="12"/><circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"/><line x1="4" x2="20" y1="18" y2="18"/><circle cx="8" cy="18" r="2" fill="currentColor" stroke="none"/></svg>
            </button>
        </div>

        {{-- Phone-only filter dropdown, toggled by the floating filter button above --}}
        <div class="emap-filters" x-show="filtersOpen" x-cloak x-transition.opacity @click.outside="filtersOpen = false">
            @include('components.map-explorer.category-filters', ['categories' => $this->categories, 'activeCategories' => $activeCategories])
        </div>

        {{-- Bottom carousel: horizontal POI cards for experiences in the map viewport --}}
        <div
            class="emap-carousel"
            x-show="visibleExperiences.length > 0 && detailMode !== 'full'"
            x-cloak
            role="list"
            aria-label="{{ __('map.carousel_label') }}"
        >
            <template x-for="experience in visibleExperiences" :key="experience.id">
                <div
                    class="emap-carousel__card"
                    role="listitem"
                    x-data="{ showOriginal: false }"
                    :class="{ 'is-selected': selectedId === experience.id }"
                    :data-experience-id="experience.id"
                    @click="selectExperience(experience); openFullDetail()"
                >
                    <div
                        class="emap-carousel__thumb"
                        :class="{ 'emap-carousel__thumb--empty': !experience.photo_url }"
                        :style="!experience.photo_url && experience.category?.color
                            ? { '--cat-color': experience.category.color }
                            : {}"
                    >
                        <template x-if="experience.photo_url">
                            <img :src="experience.photo_url" :alt="experience.title">
                        </template>
                    </div>
                    <div class="emap-carousel__body">
                        <span class="emap-carousel__cat" x-show="experience.category">
                            <span
                                class="emap-carousel__dot"
                                :style="{ background: experience.category?.color || '#FF6600' }"
                                aria-hidden="true"
                            ></span>
                            <span x-text="experience.category?.name"></span>
                        </span>
                        {{-- Titles are never translated: the author's original text in every locale. --}}
                        <div class="emap-carousel__title" x-text="experience.title"></div>
                        <button
                            type="button"
                            class="em-exp__flag emap-carousel__flag"
                            x-show="experience.is_translated"
                            @click.stop="showOriginal = ! showOriginal"
                            :aria-pressed="showOriginal ? 'true' : 'false'"
                            aria-label="{{ __('map.translation_toggle_aria') }}"
                        >
                            <span x-show="! showOriginal">{{ __('map.translated_show_original') }} <span x-text="localeFlag(experience.source_locale)"></span></span>
                            <span x-show="showOriginal" x-cloak>{{ __('map.show_translation') }}</span>
                        </button>
                    </div>
                    <button type="button" class="emap-carousel__arrow" @click.stop="selectExperience(experience); openFullDetail()" aria-label="{{ __('map.read_story') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Spot detail: overlays the map panel only (sidebar stays visible) --}}
        <div class="emap-full" x-show="detailMode === 'full'" x-cloak role="dialog" :aria-label="selectedExperience?.title">
            <template x-if="selectedExperience">
                <div style="display:contents" x-data="{ showOriginal: false }" x-effect="selectedExperience?.id; showOriginal = false">
                    <div
                        class="emap-full__photo"
                        :class="{ 'emap-full__photo--empty': !selectedExperience.photo_url }"
                        :style="!selectedExperience.photo_url ? { '--cat-color': selectedExperience.category?.color } : {}"
                    >
                        <button type="button" class="emap-full__back" @click="closeDetail()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                            <span>{{ __('map.back_to_map') }}</span>
                        </button>
                        <template x-if="selectedExperience.photo_url">
                            <img :src="selectedExperience.photo_url" :alt="selectedExperience.title" loading="lazy">
                        </template>
                        <span class="emap-full__cat" x-show="selectedExperience.category">
                            <span
                                class="emap-full__dot"
                                :style="{ background: selectedExperience.category?.color || '#FF6600' }"
                                aria-hidden="true"
                            ></span>
                            <span x-text="selectedExperience.category?.name"></span>
                        </span>
                    </div>

                    <div class="emap-full__body">
                        <span class="emap-full__loc" x-show="selectedExperience.neighborhood">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span x-text="selectedExperience.neighborhood"></span>
                        </span>
                        <h2 class="emap-full__title" x-text="selectedExperience.title"></h2>
                        <button
                            type="button"
                            class="em-exp__flag emap-full__flag"
                            x-show="selectedExperience.is_translated"
                            @click="showOriginal = ! showOriginal"
                            :aria-pressed="showOriginal ? 'true' : 'false'"
                            aria-label="{{ __('map.translation_toggle_aria') }}"
                        >
                            <span x-show="! showOriginal">{{ __('map.translated_show_original') }} <span x-text="localeFlag(selectedExperience.source_locale)"></span></span>
                            <span x-show="showOriginal" x-cloak>{{ __('map.show_translation') }}</span>
                        </button>
                        <p class="emap-full__desc" x-text="showOriginal ? selectedExperience.original_description : selectedExperience.description"></p>
                        <div class="emap-full__foot">
                            <span class="emap-full__date" x-text="selectedExperience.date" x-show="selectedExperience.date"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
