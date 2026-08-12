<?php

namespace Database\Factories;

use App\Models\Neighbourhood;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Neighbourhood>
 */
class NeighbourhoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $centerLat = 51.4416 + fake()->randomFloat(4, -0.04, 0.04);
        $centerLng = 5.4697 + fake()->randomFloat(4, -0.05, 0.05);
        $delta = 0.01;

        return [
            'wijkcode' => fake()->unique()->numberBetween(11, 73),
            'wijknaam' => 'Wijk '.fake()->unique()->city(),
            'stadsdeelnaam' => 'Stadsdeel '.fake()->city(),
            'boundary' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$centerLng - $delta, $centerLat - $delta],
                    [$centerLng + $delta, $centerLat - $delta],
                    [$centerLng + $delta, $centerLat + $delta],
                    [$centerLng - $delta, $centerLat + $delta],
                    [$centerLng - $delta, $centerLat - $delta],
                ]],
            ],
            'center_latitude' => $centerLat,
            'center_longitude' => $centerLng,
        ];
    }
}
