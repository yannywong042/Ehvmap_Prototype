<?php

namespace Tests\Feature;

use App\Models\Experience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_with_title_intro_and_cta(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('home.hero_title'));
        $response->assertSee(__('home.hero_lead'));
        $response->assertSee(__('home.hero_cta_map'));
        $response->assertSee(__('home.hero_cta_submit'));
        $response->assertSee('href="'.route('map').'" class="em-btn em-btn--primary em-btn--lg"', false);
        $response->assertSee('href="'.route('submit').'" class="em-btn em-btn--secondary em-btn--lg"', false);
    }

    public function test_homepage_renders_in_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('home'));

        $response->assertOk();
        $response->assertSee('Discover hidden gems in Eindhoven');
    }

    public function test_homepage_renders_in_dutch(): void
    {
        $response = $this->withSession(['locale' => 'nl'])->get(route('home'));

        $response->assertOk();
        $response->assertSee('Ontdek verborgen plekken in Eindhoven');
    }

    public function test_homepage_renders_how_it_works_steps(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('home.steps_title'));
        $response->assertSee(__('home.step_1_title'));
        $response->assertSee(__('home.step_2_title'));
        $response->assertSee(__('home.step_3_title'));
        $response->assertSee('steps-map__card', false);
        $response->assertSee('steps-map__title-row', false);
    }

    public function test_homepage_featured_cards_are_wrapped_for_equal_height(): void
    {
        Experience::factory()->count(3)->approved()->create();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('featured__grid', false);
        $response->assertSee('featured__card-link', false);
        $response->assertSee('em-exp', false);
    }

    public function test_homepage_shows_featured_experiences_when_present(): void
    {
        Experience::factory()->create([
            'title' => 'Karpendonkse Plas',
            'status' => 'approved',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Karpendonkse Plas');
    }
}
