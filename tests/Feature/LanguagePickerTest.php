<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguagePickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_renders_language_picker_dropdown(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('locale-picker', escape: false);
        $response->assertSee('locale-picker__toggle', escape: false);
        $response->assertSee('aria-controls="locale-menu"', escape: false);
    }

    public function test_language_picker_lists_both_locales_with_flags(): void
    {
        $response = $this->get(route('home'));

        $response->assertSee('🇬🇧', escape: false);
        $response->assertSee('🇳🇱', escape: false);
        $response->assertSee('English', escape: false);
        $response->assertSee('Nederlands', escape: false);
    }

    public function test_language_picker_posts_to_locale_switch_route_for_each_locale(): void
    {
        $response = $this->get(route('home'));

        $response->assertSee(route('locale.switch', 'en'), escape: false);
        $response->assertSee(route('locale.switch', 'nl'), escape: false);
    }

    public function test_current_locale_is_marked_active_in_picker(): void
    {
        $response = $this->withSession(['locale' => 'nl'])->get(route('home'));

        $response->assertOk();
        $response->assertSee('aria-current="true"', escape: false);
    }

    public function test_locale_switch_route_stores_locale_in_session(): void
    {
        $this->post(route('locale.switch', 'nl'))
            ->assertRedirect();

        $this->assertSame('nl', session('locale'));
    }
}
