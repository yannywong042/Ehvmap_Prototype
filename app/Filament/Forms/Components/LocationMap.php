<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class LocationMap extends Field
{
    protected string $view = 'filament.forms.components.location-map';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    public function latitudeField(string $field): static
    {
        $this->latitudeField = $field;

        return $this;
    }

    public function longitudeField(string $field): static
    {
        $this->longitudeField = $field;

        return $this;
    }

    public function getLatitudeStatePath(): string
    {
        return $this->prefixWithContainerStatePath($this->latitudeField);
    }

    public function getLongitudeStatePath(): string
    {
        return $this->prefixWithContainerStatePath($this->longitudeField);
    }

    public function getMapboxToken(): ?string
    {
        return config('services.mapbox.token');
    }

    protected function prefixWithContainerStatePath(string $field): string
    {
        $containerStatePath = $this->getContainer()->getStatePath();

        return filled($containerStatePath) ? "{$containerStatePath}.{$field}" : $field;
    }
}
