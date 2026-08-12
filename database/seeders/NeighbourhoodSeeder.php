<?php

namespace Database\Seeders;

use App\Models\Neighbourhood;
use App\Support\NeighbourhoodBoundaryLoader;
use Illuminate\Database\Seeder;

class NeighbourhoodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Loads the 20 official Eindhoven wijken from the bundled GeoJSON
     * dataset (resources/data/eindhoven-wijken.geojson) so the boundary
     * data has a single source of truth shared with any future
     * point-in-polygon lookup service.
     */
    public function run(): void
    {
        $wijken = app(NeighbourhoodBoundaryLoader::class)->load();

        foreach ($wijken as $wijk) {
            Neighbourhood::query()->updateOrCreate(
                ['wijkcode' => $wijk['wijkcode']],
                [
                    'wijknaam' => $wijk['wijknaam'],
                    'stadsdeelnaam' => $wijk['stadsdeelnaam'],
                    'boundary' => $wijk['boundary'],
                    'center_latitude' => $wijk['center_latitude'],
                    'center_longitude' => $wijk['center_longitude'],
                ]
            );
        }
    }
}
