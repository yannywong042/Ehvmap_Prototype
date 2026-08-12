@php
    $fieldWrapperView = $getFieldWrapperView();
    $latStatePath = $getLatitudeStatePath();
    $lngStatePath = $getLongitudeStatePath();
    $mapboxToken = $getMapboxToken();
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <x-location-picker
        :lat-path="$latStatePath"
        :lng-path="$lngStatePath"
        :token="$mapboxToken"
        :search-placeholder="__('Zoek op adres in Eindhoven…')"
        :search-label="__('Zoeken')"
        :hint="__('Sleep de pin of klik op de kaart om de locatie aan te passen.')"
        :out-of-bounds-message="__('Kies een locatie binnen Eindhoven.')"
        :unavailable-message="__('De kaart kan niet worden geladen omdat er geen Mapbox-token is ingesteld.')"
    />
</x-dynamic-component>
