<?php

namespace Tests\Feature;

use Tests\TestCase;

class AboutPageTest extends TestCase
{
    public function test_about_page_renders_the_real_story_in_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('about'));

        $response->assertOk();
        $response->assertSeeText(__('about.hero_title', locale: 'en'));
        $response->assertSeeText('city-makers working within the Stadsmakersprogramma');
        $response->assertSeeText('Tongelre neighbourhood');
        $response->assertSeeText('Martijn and Lotte');
    }

    public function test_about_page_renders_the_real_story_in_dutch(): void
    {
        $response = $this->withSession(['locale' => 'nl'])->get(route('about'));

        $response->assertOk();
        $response->assertSeeText(__('about.hero_title', locale: 'nl'));
        $response->assertSeeText('enthousiast team stadsmakers');
        $response->assertSeeText('wijk Tongelre');
        $response->assertSeeText('groetjes Martijn en Lotte');
    }

    public function test_dutch_copy_uses_the_corrected_spelling(): void
    {
        $response = $this->withSession(['locale' => 'nl'])->get(route('about'));

        $response->assertSeeText('hoe de stad wordt beleefd');
        $response->assertDontSee('word beleefd', escape: false);
    }

    public function test_english_and_dutch_lang_files_export_identical_keys(): void
    {
        $en = array_keys(trans('about', [], 'en'));
        $nl = array_keys(trans('about', [], 'nl'));

        sort($en);
        sort($nl);

        $this->assertSame($en, $nl, 'lang/en/about.php and lang/nl/about.php must export an identical set of keys.');
    }

    public function test_no_about_key_resolves_to_a_raw_key_or_empty_string(): void
    {
        foreach (['en', 'nl'] as $locale) {
            foreach (trans('about', [], $locale) as $key => $value) {
                $this->assertIsString($value);
                $this->assertNotSame('', trim($value), "about.{$key} is empty in {$locale}.");
                $this->assertStringNotContainsString('about.', $value, "about.{$key} looks like an unresolved key in {$locale}.");
            }
        }
    }

    public function test_removed_placeholder_copy_is_gone_in_both_locales(): void
    {
        $removed = [
            'Community team', 'Communityteam',
            'Design & maps', 'Design & kaarten',
            'Founders', 'Oprichters',
            'small group of Eindhoven locals', 'klein groepje Eindhovenaren',
        ];

        foreach (['en', 'nl'] as $locale) {
            $response = $this->withSession(['locale' => $locale])->get(route('about'));

            foreach ($removed as $phrase) {
                $response->assertDontSee($phrase, escape: false);
            }
        }
    }

    public function test_dutch_about_alias_redirects_to_canonical_route(): void
    {
        $response = $this->get('/over');

        $response->assertRedirect('/about');
    }
}
