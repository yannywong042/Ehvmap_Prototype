<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Neighbourhood;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeighbourhoodTest extends TestCase
{
    use RefreshDatabase;

    public function test_neighbourhood_casts_boundary_to_array(): void
    {
        $neighbourhood = Neighbourhood::factory()->create([
            'wijkcode' => 11,
            'wijknaam' => 'Wijk Centrum',
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[5.0, 51.0], [5.1, 51.0], [5.1, 51.1], [5.0, 51.0]]]],
        ]);

        $this->assertIsArray($neighbourhood->boundary);
        $this->assertSame('Polygon', $neighbourhood->boundary['type']);
    }

    public function test_slug_is_derived_from_wijknaam(): void
    {
        $neighbourhood = Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Gestel']);

        $this->assertSame('wijk-oud-gestel', $neighbourhood->slug());
    }

    public function test_to_array_includes_expected_keys(): void
    {
        $neighbourhood = Neighbourhood::factory()->create([
            'wijkcode' => 41,
            'wijknaam' => 'Wijk Oud-Woensel',
            'stadsdeelnaam' => 'Stadsdeel Woensel',
            'center_latitude' => 51.45,
            'center_longitude' => 5.46,
        ]);

        $array = $neighbourhood->toArray();

        $this->assertSame(41, $array['wijkcode']);
        $this->assertSame('Wijk Oud-Woensel', $array['name']);
        $this->assertSame('wijk-oud-woensel', $array['slug']);
        $this->assertSame('Stadsdeel Woensel', $array['district']);
        $this->assertSame(['lat' => 51.45, 'lng' => 5.46], $array['center']);
    }

    public function test_neighbourhood_has_many_experiences(): void
    {
        $neighbourhood = Neighbourhood::factory()->create();

        Experience::factory()->count(2)->create(['neighbourhood_id' => $neighbourhood->id]);

        $this->assertCount(2, $neighbourhood->experiences);
    }

    public function test_wijkcode_must_be_unique(): void
    {
        Neighbourhood::factory()->create(['wijkcode' => 99]);

        $this->expectException(QueryException::class);

        Neighbourhood::factory()->create(['wijkcode' => 99]);
    }
}
