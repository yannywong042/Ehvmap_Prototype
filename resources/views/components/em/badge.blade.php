@props(['tone' => 'default', 'dot' => false])
<span {{ $attributes->merge(['class' => "em-badge em-badge--{$tone}"]) }}>
    @if ($dot)<span class="em-badge__dot"></span>@endif
    {{ $slot }}
</span>
