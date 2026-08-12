<?php

namespace App\Services;

use App\Support\NeighbourhoodBoundaryLoader;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Determines which official Eindhoven wijk (neighbourhood) contains a given
 * coordinate, using a point-in-polygon check against the bundled wijk
 * boundary GeoJSON dataset (see NeighbourhoodBoundaryLoader).
 */
class WijkLookup
{
    public function __construct(private readonly NeighbourhoodBoundaryLoader $loader) {}

    /**
     * Returns the wijknaam of the wijk polygon containing the given
     * coordinate, or null if no polygon matches or the dataset can't be
     * loaded/parsed.
     *
     * If a point falls on a shared border between two wijken, the first
     * matching polygon in the dataset wins deterministically.
     */
    public function find(float $latitude, float $longitude): ?string
    {
        try {
            $wijken = $this->loader->load();
        } catch (Throwable $e) {
            Log::error('Failed to load wijk boundary dataset for WijkLookup.', [
                'exception' => $e,
            ]);

            return null;
        }

        foreach ($wijken as $wijk) {
            if ($this->geometryContainsPoint($wijk['boundary'] ?? null, $latitude, $longitude)) {
                return $wijk['wijknaam'] !== '' ? $wijk['wijknaam'] : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $geometry  GeoJSON Polygon or MultiPolygon geometry
     */
    private function geometryContainsPoint(?array $geometry, float $latitude, float $longitude): bool
    {
        if (! is_array($geometry) || ! isset($geometry['type'], $geometry['coordinates'])) {
            return false;
        }

        if ($geometry['type'] === 'Polygon') {
            return $this->polygonContainsPoint($geometry['coordinates'], $latitude, $longitude);
        }

        if ($geometry['type'] === 'MultiPolygon') {
            foreach ($geometry['coordinates'] as $polygon) {
                if ($this->polygonContainsPoint($polygon, $latitude, $longitude)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A GeoJSON Polygon's coordinates are a list of linear rings: the first
     * is the outer boundary, any subsequent rings are holes to subtract.
     *
     * @param  array<int, array<int, array<int, float>>>  $rings
     */
    private function polygonContainsPoint(array $rings, float $latitude, float $longitude): bool
    {
        if ($rings === []) {
            return false;
        }

        $outerRing = $rings[0];

        if (! $this->ringContainsPoint($outerRing, $latitude, $longitude)) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if ($this->ringContainsPoint($hole, $latitude, $longitude)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Standard ray-casting point-in-polygon algorithm. GeoJSON coordinates
     * are [longitude, latitude] pairs.
     *
     * @param  array<int, array<int, float>>  $ring
     */
    private function ringContainsPoint(array $ring, float $latitude, float $longitude): bool
    {
        $inside = false;
        $vertexCount = count($ring);

        for ($i = 0, $j = $vertexCount - 1; $i < $vertexCount; $j = $i++) {
            $xi = (float) ($ring[$i][0] ?? 0);
            $yi = (float) ($ring[$i][1] ?? 0);
            $xj = (float) ($ring[$j][0] ?? 0);
            $yj = (float) ($ring[$j][1] ?? 0);

            if ($this->pointOnSegment($longitude, $latitude, $xi, $yi, $xj, $yj)) {
                return true;
            }

            $intersects = ($yi > $latitude) !== ($yj > $latitude)
                && $longitude < ($xj - $xi) * ($latitude - $yi) / ($yj - $yi) + $xi;

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Detects whether (px, py) lies exactly on the segment between (x1, y1)
     * and (x2, y2), so points on a shared wijk border resolve deterministically
     * instead of depending on floating-point ray-casting edge effects.
     */
    private function pointOnSegment(float $px, float $py, float $x1, float $y1, float $x2, float $y2): bool
    {
        $cross = ($px - $x1) * ($y2 - $y1) - ($py - $y1) * ($x2 - $x1);

        if (abs($cross) > 1e-9) {
            return false;
        }

        return $px >= min($x1, $x2) && $px <= max($x1, $x2)
            && $py >= min($y1, $y2) && $py <= max($y1, $y2);
    }
}
