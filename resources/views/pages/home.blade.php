<x-layouts.website bodyClass="site--home">
    <div class="page-home">
        {{-- Hero — split panel (brand color + map) --}}
        <section class="hero">
            <div class="hero__inner">
                <h1 class="hero__title">{{ __('home.hero_title') }}</h1>
                <p class="hero__lead">{{ __('home.hero_lead') }}</p>
                <div class="hero__actions">
                    <x-em.button href="{{ route('map') }}" variant="primary" size="lg">
                        {{ __('home.hero_cta_map') }}
                    </x-em.button>
                    <x-em.button href="{{ route('submit') }}" variant="secondary" size="lg">
                        {{ __('home.hero_cta_submit') }}
                    </x-em.button>
                </div>
                @if ($totalCount > 0)
                    <div class="hero__stats">
                        <div>
                            <strong>{{ $totalCount }}</strong>
                            <span>{{ __('home.hero_stat_label') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <a
                href="{{ route('map') }}"
                class="mini-map"
                aria-label="{{ __('home.hero_cta_map') }}"
                x-data="miniMapPreview()"
                x-init="initMap(@js($mapPoints))"
            >
                <div class="mini-map__canvas" x-ref="canvas"></div>
            </a>
        </section>

        {{-- How it works — editorial step cards --}}
        <section class="home-section steps-map">
            <h2 class="steps-title">{{ __('home.steps_title') }}</h2>

            <ol class="steps-map__stops">
                <li class="steps-map__card">
                    <div class="steps-map__corner">
                        <div class="steps-map__title-row">
                            <span class="steps-map__no">1</span>
                            <h3 class="steps-map__heading">{{ __('home.step_1_title') }}</h3>
                        </div>
                    </div>
                    <p class="steps-map__desc">{{ __('home.step_1_desc') }}</p>
                </li>
                <li class="steps-map__card">
                    <div class="steps-map__corner">
                        <div class="steps-map__title-row">
                            <span class="steps-map__no">2</span>
                            <h3 class="steps-map__heading">{{ __('home.step_2_title') }}</h3>
                        </div>
                    </div>
                    <p class="steps-map__desc">{{ __('home.step_2_desc') }}</p>
                </li>
                <li class="steps-map__card">
                    <div class="steps-map__corner">
                        <div class="steps-map__title-row">
                            <span class="steps-map__no">3</span>
                            <h3 class="steps-map__heading">{{ __('home.step_3_title') }}</h3>
                        </div>
                    </div>
                    <p class="steps-map__desc">{{ __('home.step_3_desc') }}</p>
                </li>
            </ol>
        </section>

        {{-- Featured experiences --}}
        @if ($experiences->isNotEmpty())
            <section class="featured home-section">
                <div class="featured__head">
                    <h2>{{ __('home.featured_title') }}</h2>
                    <a href="{{ route('map') }}" class="featured__view-all">{{ __('home.featured_view_all') }}</a>
                </div>
                <div class="featured__grid">
                    @foreach ($experiences as $experience)
                        <a href="{{ route('map') }}" class="featured__card-link">
                            <x-em.experience-card :experience="$experience" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.website>
