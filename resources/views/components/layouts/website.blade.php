<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $bodyClass ?? '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Eindhoven Mapped' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon-32.png') }}?v=3" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- TAN Headline is licensed (TanType). Fraunces is the free fallback until /public/fonts/TANHeadline-*.{woff2,otf} is added. Body uses self-hosted Glacial Indifference. --}}
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <meta name="mapbox-token" content="{{ config('services.mapbox.token') }}">
    @vite(['resources/css/app.css', 'resources/js/website.js'])
    @livewireStyles
</head>
<body class="{{ $bodyClass ?? '' }}">
    <div class="em-page site {{ $bodyClass ?? '' }}">
        <x-em.site-header />
        <main class="site-main">{{ $slot }}</main>
        @if (!($hideFooter ?? false))
            <x-em.site-footer />
        @endif
    </div>
    @livewireScripts
</body>
</html>
