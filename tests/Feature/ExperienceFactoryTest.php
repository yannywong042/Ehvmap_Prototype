<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Experience;
use App\Models\Neighbourhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExperienceFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_state_sets_rejected_status(): void
    {
        $experience = Experience::factory()->rejected()->create();

        $this->assertSame('rejected', $experience->status);
        $this->assertTrue(Experience::rejected()->whereKey($experience->id)->exists());
    }

    public function test_factory_creates_a_canonical_category_when_none_exist(): void
    {
        // The categories table is pre-populated with the five canonical rows
        // directly by the create_categories_table migration, so this must be
        // cleared to exercise the factory's own on-demand creation.
        Category::query()->delete();
        $this->assertSame(0, Category::count());

        $experience = Experience::factory()->create();

        $this->assertNotNull($experience->category_id);
        $this->assertArrayHasKey($experience->category->slug, Category::CANONICAL);
    }

    public function test_factory_creates_a_neighbourhood_when_none_exist(): void
    {
        $this->assertSame(0, Neighbourhood::count());

        $experience = Experience::factory()->create();

        $this->assertNotNull($experience->neighbourhood_id);
        $this->assertModelExists($experience->neighbourhood);
    }

    public function test_bulk_creation_does_not_issue_a_lookup_query_per_row(): void
    {
        Neighbourhood::factory()->create();

        DB::enableQueryLog();
        Experience::factory()->count(20)->create();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(30, $queryCount);
    }
}
