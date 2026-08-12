<?php

namespace Tests\Feature;

use App\Models\Neighbourhood;
use Database\Seeders\NeighbourhoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeighbourhoodSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_twenty_official_eindhoven_wijken(): void
    {
        $this->seed(NeighbourhoodSeeder::class);

        $this->assertSame(20, Neighbourhood::count());
    }

    public function test_seeder_assigns_wijkcodes_in_official_range(): void
    {
        $this->seed(NeighbourhoodSeeder::class);

        $wijkcodes = Neighbourhood::query()->pluck('wijkcode');

        $this->assertTrue($wijkcodes->every(fn (int $code) => $code >= 11 && $code <= 73));
        $this->assertSame($wijkcodes->unique()->count(), $wijkcodes->count());
    }

    public function test_seeder_stores_boundary_geometry_for_each_wijk(): void
    {
        $this->seed(NeighbourhoodSeeder::class);

        $centrum = Neighbourhood::query()->where('wijkcode', 11)->firstOrFail();

        $this->assertSame('Wijk Centrum', $centrum->wijknaam);
        $this->assertIsArray($centrum->boundary);
        $this->assertArrayHasKey('type', $centrum->boundary);
        $this->assertArrayHasKey('coordinates', $centrum->boundary);
        $this->assertNotNull($centrum->center_latitude);
        $this->assertNotNull($centrum->center_longitude);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(NeighbourhoodSeeder::class);
        $this->seed(NeighbourhoodSeeder::class);

        $this->assertSame(20, Neighbourhood::count());
    }
}
