<?php

namespace Tests\Unit;

use App\Services\WijkLookup;
use App\Support\NeighbourhoodBoundaryLoader;
use Tests\TestCase;

class WijkLookupTest extends TestCase
{
    public function test_finds_wijk_for_point_clearly_inside_its_polygon(): void
    {
        $lookup = new WijkLookup(new NeighbourhoodBoundaryLoader);

        // Inside Wijk Centrum's polygon (verified against the bundled GeoJSON).
        $result = $lookup->find(51.4407636157305, 5.482401879910195);

        $this->assertSame('Wijk Centrum', $result);
    }

    public function test_returns_null_for_point_inside_eindhoven_bbox_but_outside_all_wijken(): void
    {
        $lookup = new WijkLookup(new NeighbourhoodBoundaryLoader);

        // Inside the Eindhoven municipality bounding box used by the submit
        // form, but outside all 20 wijk polygons (e.g. a corner gap).
        $result = $lookup->find(51.385, 5.385);

        $this->assertNull($result);
    }

    public function test_point_on_a_polygon_edge_resolves_deterministically(): void
    {
        $lookup = new WijkLookup(new NeighbourhoodBoundaryLoader);

        // Midpoint of an edge of Wijk Centrum's boundary ring — exercises the
        // ray-casting edge handling, deterministically resolving to a wijk.
        $result = $lookup->find(51.45238768605839, 5.492701933495951);

        $this->assertSame('Wijk Centrum', $result);
    }

    public function test_returns_null_for_clearly_outside_coordinate(): void
    {
        $lookup = new WijkLookup(new NeighbourhoodBoundaryLoader);

        // Amsterdam — far outside any Eindhoven wijk polygon.
        $result = $lookup->find(52.3676, 4.9041);

        $this->assertNull($result);
    }
}
