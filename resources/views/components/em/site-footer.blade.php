<footer class="site-footer">
    <div class="site-footer__inner">
        <a href="{{ route('home') }}" class="site-logo site-logo--sm" aria-label="Eindhoven Mapped — home">
            <img src="{{ asset('images/logo-mark.webp') }}" alt="" width="24" height="24" class="site-logo__mark" aria-hidden="true">
            <span class="site-logo__wm"><span>Eindhoven</span> <em>Mapped</em></span>
        </a>

        <nav class="site-footer__nav">
            <a href="{{ route('map') }}">{{ __('site.nav_map') }}</a>
            <a href="{{ route('about') }}">{{ __('site.nav_about') }}</a>
            <a href="{{ route('submit') }}">{{ __('site.nav_submit') }}</a>
            <a href="{{ route('terms') }}">{{ __('site.nav_terms') }}</a>
        </nav>

        <p class="site-footer__copy">
            &copy; {{ date('Y') }} Eindhoven Mapped &mdash; {{ __('site.footer_tagline') }}
        </p>
    </div>
</footer>
