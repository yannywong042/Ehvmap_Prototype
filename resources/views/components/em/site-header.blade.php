@props(['neighborhoods' => []])
@php
    $locales = [
        'en' => ['flag' => '🇬🇧', 'label' => 'EN', 'name' => 'English'],
        'nl' => ['flag' => '🇳🇱', 'label' => 'NL', 'name' => 'Nederlands'],
    ];
    $currentLocale = app()->getLocale();
    $current = $locales[$currentLocale] ?? reset($locales);
@endphp
<header
    class="site-header"
    x-data="{
        mobileOpen: false,
        open() {
            this.mobileOpen = true;
            document.body.classList.add('is-nav-locked');
        },
        close() {
            this.mobileOpen = false;
            document.body.classList.remove('is-nav-locked');
        },
        toggle() {
            this.mobileOpen ? this.close() : this.open();
        },
        onResize() {
            if (this.mobileOpen && window.innerWidth > 760) {
                this.close();
            }
        },
        trapTab(event) {
            if (!this.mobileOpen || window.innerWidth > 760) {
                return;
            }
            const focusable = this.$refs.nav.querySelectorAll('a[href], button:not([disabled])');
            if (focusable.length === 0) {
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    x-on:resize.window="onResize()"
    x-on:keydown.escape.window="if (mobileOpen) { close(); $refs.hamburger.focus(); }"
    x-on:keydown.tab="trapTab($event)"
>
    <div class="site-header__inner">
        <a href="{{ route('home') }}" class="site-logo" aria-label="Eindhoven Mapped — home">
            <img src="{{ asset('images/logo-mark.webp') }}" alt="" width="32" height="32" class="site-logo__mark" aria-hidden="true">
            <span class="site-logo__wm"><span>Eindhoven</span> <em>Mapped</em></span>
        </a>

        <nav
            class="site-nav"
            id="site-nav"
            x-ref="nav"
            :class="{ 'is-open': mobileOpen }"
            x-on:click.outside="if (mobileOpen && !$event.target.closest('.site-header__hamburger')) close()"
            x-on:click="if ($event.target.closest('a, button')) close()"
        >
            <a href="{{ route('map') }}" class="site-nav__link {{ request()->routeIs('map') ? 'is-active' : '' }}">{{ __('site.nav_map') }}</a>
            <a href="{{ route('about') }}" class="site-nav__link {{ request()->routeIs('about') ? 'is-active' : '' }}">{{ __('site.nav_about') }}</a>
            <a href="{{ route('submit') }}" class="em-btn em-btn--primary em-btn--sm">{{ __('site.nav_submit') }}</a>
        </nav>

        <div class="site-header__right">
            <div
                class="locale-picker"
                x-data="{ open: false }"
                @keydown.escape.window="open = false"
                @click.outside="open = false"
            >
                <button
                    type="button"
                    class="locale-picker__toggle"
                    @click="open = !open"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-controls="locale-menu"
                    aria-label="{{ __('site.change_language') }}"
                >
                    <span class="locale-picker__flag" aria-hidden="true">{{ $current['flag'] }}</span>
                    <span class="locale-picker__label">{{ $current['label'] }}</span>
                    <svg class="locale-picker__chevron" :class="{ 'is-open': open }" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                        <path d="M2.5 4.5L6 8l3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <ul
                    class="locale-picker__menu"
                    id="locale-menu"
                    x-show="open"
                    x-transition.opacity.duration.120ms
                    x-cloak
                >
                    @foreach ($locales as $code => $locale)
                        <li>
                            <form method="POST" action="{{ route('locale.switch', $code) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="locale-picker__opt {{ $currentLocale === $code ? 'is-active' : '' }}"
                                    @if ($currentLocale === $code) aria-current="true" @endif
                                >
                                    <span class="locale-picker__flag" aria-hidden="true">{{ $locale['flag'] }}</span>
                                    <span class="locale-picker__optname">{{ $locale['name'] }}</span>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>

            <button
                class="site-header__hamburger"
                x-ref="hamburger"
                @click="toggle()"
                aria-label="Menu"
                aria-controls="site-nav"
                :aria-expanded="mobileOpen"
            >
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>
