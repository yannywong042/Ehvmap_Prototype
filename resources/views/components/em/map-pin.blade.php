@props(['color' => 'orange', 'selected' => false, 'size' => 'md'])
<div {{ $attributes->merge(['class' => "em-pin em-pin--{$size}" . ($selected ? ' is-selected' : '')]) }}>
    <svg viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M16 2C8.27 2 2 8.27 2 16c0 4.2 2.2 8.4 5.3 12.1C10.4 31.8 13.8 34.8 16 36.5c2.2-1.7 5.6-4.7 8.7-8.4C27.8 24.4 30 20.2 30 16 30 8.27 23.73 2 16 2z" fill="{{ $color === 'orange' ? '#FF6600' : 'currentColor' }}"/>
        <circle cx="16" cy="16" r="5" fill="white"/>
    </svg>
    @if ($slot->isNotEmpty())
        <span class="em-pin__label">{{ $slot }}</span>
    @endif
</div>
