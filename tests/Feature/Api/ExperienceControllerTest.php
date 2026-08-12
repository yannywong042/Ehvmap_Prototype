<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Experience;
use App\Models\Neighbourhood;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_experience_includes_category(): void
    {
        $this->seed(CategorySeeder::class);

        $category = Category::where('slug', 'natuur')->firstOrFail();

        $experience = Experience::factory()->approved()->create([
            'category_id' => $category->id,
            'latitude' => 51.4401,
            'longitude' => 5.4789,
        ]);

        $response = $this->getJson(route('api.experiences'));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $experience->id,
            'category' => [
                'id' => $category->id,
                'slug' => 'natuur',
                'name' => 'Nature',
                'color' => '#1B9C84',
            ],
        ]);
    }

    public function test_experience_with_category_suggestion_has_null_category(): void
    {
        $experience = Experience::factory()->approved()->create([
            'category_id' => null,
            'category_suggestion' => 'Boerenmarkt',
            'latitude' => 51.4401,
            'longitude' => 5.4789,
        ]);

        $response = $this->getJson(route('api.experiences'));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $experience->id,
            'category' => null,
        ]);
    }

    public function test_response_includes_neighbourhood_id(): void
    {
        $neighbourhood = Neighbourhood::factory()->create();

        $experience = Experience::factory()->approved()->create([
            'neighbourhood_id' => $neighbourhood->id,
            'latitude' => 51.4401,
            'longitude' => 5.4789,
        ]);

        $response = $this->getJson(route('api.experiences'));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $experience->id,
            'neighbourhood_id' => $neighbourhood->id,
        ]);
    }
}
