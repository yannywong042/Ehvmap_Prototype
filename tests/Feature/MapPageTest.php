<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Experience;
use App\Models\Neighbourhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MapPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_page_includes_experience_carousel_markup(): void
    {
        Experience::factory()->create([
            'title' => 'Carousel pin',
            'status' => 'published',
            'latitude' => 51.4401,
            'longitude' => 5.4789,
        ]);

        $response = $this->get(route('map'));

        $response->assertOk();
        $response->assertSee('emap-carousel', false);
        $response->assertSee('emap-carousel__card', false);
        $response->assertSee('emap-carousel__thumb', false);
        $response->assertSee('emap-carousel__arrow', false);
        $response->assertSee('emap-full', false);
        $response->assertSee('emap-full__back', false);
        $response->assertSee(__('map.back_to_map'), false);
        $response->assertSee(__('map.carousel_label'), false);
    }

    public function test_experiences_updated_event_dispatched_on_search(): void
    {
        Experience::factory()->create([
            'title' => 'Karpendonkse Plas',
            'status' => 'published',
            'latitude' => 51.4401,
            'longitude' => 5.4789,
        ]);

        Livewire::test('map-explorer')
            ->set('search', 'Karp')
            ->assertDispatched('experiences-updated');
    }

    public function test_experiences_updated_event_dispatched_on_category_toggle(): void
    {
        $category = Category::factory()->create();

        Experience::factory()->create([
            'title' => 'Natuur test',
            'category_id' => $category->id,
            'status' => 'published',
            'latitude' => 51.4401,
            'longitude' => 5.4789,
        ]);

        Livewire::test('map-explorer')
            ->call('toggleCategory', $category->id)
            ->assertDispatched('experiences-updated');
    }

    public function test_experiences_updated_event_dispatched_on_clear_filters(): void
    {
        Livewire::test('map-explorer')
            ->set('search', 'test')
            ->call('clearFilters')
            ->assertDispatched('experiences-updated');
    }

    public function test_experiences_updated_event_excludes_experiences_without_coordinates(): void
    {
        Experience::factory()->create([
            'title' => 'With coordinates',
            'status' => 'published',
            'latitude' => 51.4401,
            'longitude' => 5.4789,
        ]);

        Experience::factory()->create([
            'title' => 'Without coordinates',
            'status' => 'published',
            'latitude' => null,
            'longitude' => null,
        ]);

        Livewire::test('map-explorer')
            ->call('clearFilters')
            ->assertDispatched('experiences-updated', fn (string $name, array $params) => count($params['experiences']) === 1
                && $params['experiences'][0]['title'] === 'With coordinates'
            );
    }

    public function test_selecting_a_neighbourhood_filters_experiences_to_that_neighbourhood(): void
    {
        $woensel = Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);
        $centrum = Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);

        Experience::factory()->create([
            'title' => 'In Woensel',
            'status' => 'published',
            'neighbourhood_id' => $woensel->id,
            'latitude' => 51.45,
            'longitude' => 5.46,
        ]);

        Experience::factory()->create([
            'title' => 'In Centrum',
            'status' => 'published',
            'neighbourhood_id' => $centrum->id,
            'latitude' => 51.44,
            'longitude' => 5.48,
        ]);

        Livewire::test('map-explorer')
            ->call('selectNeighbourhood', $woensel->slug())
            ->assertSet('selectedNeighbourhood', $woensel->slug())
            ->assertDispatched('experiences-updated', fn (string $name, array $params) => count($params['experiences']) === 1
                && $params['experiences'][0]['title'] === 'In Woensel'
            );
    }

    public function test_clearing_neighbourhood_selection_shows_all_published_pins_again(): void
    {
        $woensel = Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);
        $centrum = Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);

        Experience::factory()->create([
            'status' => 'published',
            'neighbourhood_id' => $woensel->id,
            'latitude' => 51.45,
            'longitude' => 5.46,
        ]);

        Experience::factory()->create([
            'status' => 'published',
            'neighbourhood_id' => $centrum->id,
            'latitude' => 51.44,
            'longitude' => 5.48,
        ]);

        Livewire::test('map-explorer')
            ->call('selectNeighbourhood', $woensel->slug())
            ->call('clearFilters')
            ->assertSet('selectedNeighbourhood', null)
            ->assertDispatched('experiences-updated', fn (string $name, array $params) => count($params['experiences']) === 2);
    }

    public function test_neighbourhood_with_no_experiences_returns_empty_list(): void
    {
        $emptyNeighbourhood = Neighbourhood::factory()->create(['wijknaam' => 'Wijk Meerhoven']);

        Experience::factory()->create([
            'status' => 'published',
            'neighbourhood_id' => null,
            'latitude' => 51.44,
            'longitude' => 5.48,
        ]);

        Livewire::test('map-explorer')
            ->call('selectNeighbourhood', $emptyNeighbourhood->slug())
            ->assertDispatched('experiences-updated', fn (string $name, array $params) => count($params['experiences']) === 0);
    }

    public function test_selected_neighbourhood_persists_via_url_query_parameter(): void
    {
        $woensel = Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);

        Livewire::withQueryParams(['wijk' => $woensel->slug()])
            ->test('map-explorer')
            ->assertSet('selectedNeighbourhood', $woensel->slug());
    }

    public function test_unknown_neighbourhood_slug_in_url_resets_selection(): void
    {
        Livewire::withQueryParams(['wijk' => 'does-not-exist'])
            ->test('map-explorer')
            ->assertSet('selectedNeighbourhood', null);
    }
}
