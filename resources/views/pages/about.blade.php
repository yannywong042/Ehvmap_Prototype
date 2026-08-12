<x-layouts.website>
    <article class="about">
        <header class="about__hero">
            <p class="about__eyebrow eyebrow">{{ __('about.eyebrow') }}</p>
            <h1>{{ __('about.hero_title') }}</h1>
            <p class="about__intro">{{ __('about.hero_intro') }}</p>
        </header>

        <div class="about__letter">
            <p class="about__lede">{{ __('about.story_p1') }}</p>

            <blockquote class="about__quote">
                <p>{{ __('about.pull_quote') }}</p>
            </blockquote>

            <p>{{ __('about.story_p2') }}</p>
            <p>{{ __('about.story_p3') }}</p>
            <p>{{ __('about.story_p4') }}</p>

            <footer class="about__signoff">
                <p class="about__signoff-line">{{ __('about.signoff') }}</p>
                <p class="about__signature">{{ __('about.signature') }}</p>
            </footer>
        </div>

        <div class="about__cta">
            <h2>{{ __('about.cta_title') }}</h2>
            <p>{{ __('about.cta_text') }}</p>
            <x-em.button href="{{ route('submit') }}" variant="primary" size="lg">
                {{ __('about.cta_btn') }}
            </x-em.button>
        </div>
    </article>
</x-layouts.website>
