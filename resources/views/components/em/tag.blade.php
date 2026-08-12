@props(['color' => null, 'removable' => false])
<span {{ $attributes->merge(['class' => 'em-tag' . ($color ? " em-tag--{$color}" : '')]) }}>
    {{ $slot }}
    @if ($removable)
        <button type="button" class="em-tag__remove" aria-label="Verwijder">&times;</button>
    @endif
</span>
