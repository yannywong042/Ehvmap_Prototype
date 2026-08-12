{{-- Shared SVG sprite for Alpine category icons (carousel / full detail). --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" class="em-cat-sprite" aria-hidden="true" focusable="false">
    <defs>
        @foreach (\App\Support\CategoryIcons::PATHS as $slug => $paths)
            <symbol id="em-cat-icon-{{ $slug }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                @foreach ($paths as $path)
                    <path d="{{ $path }}" />
                @endforeach
            </symbol>
        @endforeach
    </defs>
</svg>
