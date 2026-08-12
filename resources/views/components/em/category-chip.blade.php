@props(['category' => null, 'active' => false])
@php
    $color = $category?->color ?? 'var(--brand)';
@endphp
<span {{ $attributes->merge(['class' => 'em-cat' . ($active ? ' em-cat--active' : '')]) }}
    data-category="{{ $category?->slug }}"
    style="--cat-color: {{ $color }}">
    <x-em.category-icon
        :slug="$category?->slug"
        :color="$active ? null : $color"
        :size="16"
    />
    {{ $category?->name }}
</span>
