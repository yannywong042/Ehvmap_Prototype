@props([
    'slug' => null,
    'color' => null,
    'size' => 16,
])
@php
    $resolvedSlug = \App\Support\CategoryIcons::resolveSlug(is_string($slug) ? $slug : null);
    $paths = \App\Support\CategoryIcons::pathsFor($resolvedSlug);
    $style = filled($color) ? 'color: '.$color : null;
@endphp
<svg
    {{ $attributes->class('em-cat__icon') }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2.2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    data-category-icon="{{ $resolvedSlug }}"
    @if ($style) style="{{ $style }}" @endif
>
    @foreach ($paths as $path)
        <path d="{{ $path }}" />
    @endforeach
</svg>
