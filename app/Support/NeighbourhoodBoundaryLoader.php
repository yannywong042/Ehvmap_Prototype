<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads the bundled Eindhoven wijk boundary GeoJSON dataset.
 *
 * This is the single shared source of truth for wijk boundary data, used by
 * the NeighbourhoodSeeder to populate the `neighbourhoods` table, and by the
 * `WijkLookup` point-in-polygon service to resolve a coordinate to a wijk.
 */
class NeighbourhoodBoundaryLoader
{
    /**
     * @var array<int, array{wijkcode: int, wijknaam: string, stadsdeelnaam: ?string, boundary: array<string, mixed>, center_latitude: ?float, center_longitude: ?float}>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<int, array{wijkcode: int, wijknaam: string, stadsdeelnaam: ?string, boundary: array<string, mixed>, center_latitude: ?float, center_longitude: ?float}>
     */
    public function load(): array
    {
        return self::$cache ??= $this->readFromDisk();
    }

    /**
     * @return array<int, array{wijkcode: int, wijknaam: string, stadsdeelnaam: ?string, boundary: array<string, mixed>, center_latitude: ?float, center_longitude: ?float}>
     */
    private function readFromDisk(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            throw new RuntimeException("Neighbourhood boundary dataset not found at [{$path}].");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read neighbourhood boundary dataset at [{$path}].");
        }

        /** @var array{type: string, features: list<array<string, mixed>>}|null $geojson */
        $geojson = json_decode($contents, true);

        if (! is_array($geojson) || ! isset($geojson['features']) || ! is_array($geojson['features'])) {
            throw new RuntimeException("Neighbourhood boundary dataset at [{$path}] is not valid GeoJSON.");
        }

        return array_map(function (array $feature): array {
            $properties = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? null;
            $center = $this->centroid($geometry);

            return [
                'wijkcode' => (int) ($properties['wijkcode'] ?? 0),
                'wijknaam' => (string) ($properties['wijknaam'] ?? ''),
                'stadsdeelnaam' => $properties['stadsdeelnaam'] ?? null,
                'boundary' => $geometry,
                'center_latitude' => $center['lat'] ?? null,
                'center_longitude' => $center['lng'] ?? null,
            ];
        }, $geojson['features']);
    }

    public function path(): string
    {
        return resource_path('data/eindhoven-wijken.geojson');
    }

    /**
     * Computes a simple vertex-average centroid for the polygon, used to
     * center/pan the map when a neighbourhood is selected.
     *
     * @param  array<string, mixed>|null  $geometry
     * @return array{lat: ?float, lng: ?float}
     */
    private function centroid(?array $geometry): array
    {
        if (! is_array($geometry) || ! isset($geometry['coordinates'], $geometry['type'])) {
            return ['lat' => null, 'lng' => null];
        }

        $coordinates = $geometry['type'] === 'MultiPolygon'
            ? $geometry['coordinates'][0][0] ?? []
            : $geometry['coordinates'][0] ?? [];

        if ($coordinates === []) {
            return ['lat' => null, 'lng' => null];
        }

        $lngSum = 0.0;
        $latSum = 0.0;
        $count = 0;

        foreach ($coordinates as $point) {
            if (! isset($point[0], $point[1])) {
                continue;
            }

            $lngSum += (float) $point[0];
            $latSum += (float) $point[1];
            $count++;
        }

        if ($count === 0) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => $latSum / $count,
            'lng' => $lngSum / $count,
        ];
    }
}
