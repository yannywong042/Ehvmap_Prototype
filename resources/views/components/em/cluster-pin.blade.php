@props(['count' => 0])
<div {{ $attributes->merge(['class' => 'em-cluster']) }}>
    <span class="em-cluster__count">{{ $count }}</span>
</div>
