@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
])

@php
    $classes = "em-btn em-btn--{$variant} em-btn--{$size}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<span class="em-btn__icon">{{ $icon }}</span>@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<span class="em-btn__icon">{{ $icon }}</span>@endif
        {{ $slot }}
    </button>
@endif
