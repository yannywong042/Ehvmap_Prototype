<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Services\WijkLookup;
use Database\Seeders\ExperienceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_fifty_experiences(): void
    {
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(50, Experience::count());
    }

    public function test_seeder_uses_a_realistic_status_mix(): void
    {
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(35, Experience::approved()->count());
        $this->assertSame(10, Experience::pending()->count());
        $this->assertSame(5, Experience::rejected()->count());
    }

    public function test_every_seeded_experience_has_a_category_and_neighbourhood(): void
    {
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(0, Experience::whereNull('category_id')->count());
        $this->assertSame(0, Experience::whereNull('neighbourhood_id')->count());
    }

    public function test_seeded_neighborhood_label_matches_its_neighbourhood(): void
    {
        $this->seed(ExperienceSeeder::class);

        Experience::with('neighbourhood')->get()->each(function (Experience $experience): void {
            $this->assertSame($experience->neighbourhood->wijknaam, $experience->neighborhood);
        });
    }

    public function test_seeded_coordinates_resolve_to_their_assigned_neighbourhood(): void
    {
        $this->seed(ExperienceSeeder::class);

        $lookup = app(WijkLookup::class);

        Experience::with('neighbourhood')->get()->each(function (Experience $experience) use ($lookup): void {
            $resolved = $lookup->find((float) $experience->latitude, (float) $experience->longitude);

            $this->assertSame($experience->neighbourhood->wijknaam, $resolved);
        });
    }

    public function test_seeded_titles_do_not_visibly_repeat(): void
    {
        $this->seed(ExperienceSeeder::class);

        $distinctTitles = Experience::query()->distinct('title')->count('title');

        $this->assertGreaterThan(35, $distinctTitles);
    }

    public function test_seeding_again_adds_another_batch_without_error(): void
    {
        $this->seed(ExperienceSeeder::class);
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(100, Experience::count());
    }
}
