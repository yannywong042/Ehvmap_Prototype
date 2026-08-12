<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CategoryFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_state_matches_category_seeder_for_every_slug(): void
    {
        $this->seed(CategorySeeder::class);

        foreach (array_keys(Category::CANONICAL) as $slug) {
            $seeded = Category::where('slug', $slug)->firstOrFail();
            $fromFactory = Category::factory()->canonical($slug)->make();

            $this->assertSame($seeded->slug, $fromFactory->slug);
            $this->assertSame($seeded->color, $fromFactory->color);
            $this->assertSame($seeded->name_translations, $fromFactory->name_translations);
        }
    }

    public function test_named_states_set_the_matching_canonical_slug(): void
    {
        $this->assertSame('natuur', Category::factory()->natuur()->make()->slug);
        $this->assertSame('water', Category::factory()->water()->make()->slug);
        $this->assertSame('cultuur', Category::factory()->cultuur()->make()->slug);
        $this->assertSame('verhalen', Category::factory()->verhalen()->make()->slug);
        $this->assertSame('eten', Category::factory()->eten()->make()->slug);
    }

    public function test_canonical_without_a_slug_still_produces_a_canonical_category(): void
    {
        $category = Category::factory()->canonical()->make();

        $this->assertArrayHasKey($category->slug, Category::CANONICAL);
    }

    public function test_canonical_rejects_an_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Category::factory()->canonical('not-a-real-category')->make();
    }
}
