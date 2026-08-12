<?php

namespace App\Models;

use Database\Factories\NeighbourhoodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Represents one of the 20 official Eindhoven wijken (neighbourhoods).
 *
 * Boundary data is sourced from the bundled GeoJSON dataset at
 * resources/data/eindhoven-wijken.geojson (Gemeente Eindhoven open data) and
 * loaded via the NeighbourhoodSeeder. This model is the shared source of
 * truth for both the map's neighbourhood filter and any future
 * point-in-polygon lookup service (e.g. WijkLookup) that needs the same
 * wijknaam/wijkcode/boundary data when a new experience is submitted.
 */
class Neighbourhood extends Model
{
    /** @use HasFactory<NeighbourhoodFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'wijkcode',
        'wijknaam',
        'stadsdeelnaam',
        'boundary',
        'center_latitude',
        'center_longitude',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wijkcode' => 'integer',
            'boundary' => 'array',
            'center_latitude' => 'float',
            'center_longitude' => 'float',
        ];
    }

    /**
     * @return HasMany<Experience, $this>
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'wijkcode' => $this->wijkcode,
            'name' => $this->wijknaam,
            'slug' => $this->slug(),
            'district' => $this->stadsdeelnaam,
            'boundary' => $this->boundary,
            'center' => $this->center_latitude !== null && $this->center_longitude !== null
                ? ['lat' => $this->center_latitude, 'lng' => $this->center_longitude]
                : null,
        ];
    }

    public function slug(): string
    {
        return Str::slug($this->wijknaam);
    }

    /**
     * The wijknaam without the "Wijk " prefix, for display purposes.
     */
    public function displayName(): string
    {
        return Str::of($this->wijknaam)->after('Wijk ')->trim()->toString();
    }
}
