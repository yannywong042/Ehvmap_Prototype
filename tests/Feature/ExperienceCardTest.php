<?php

namespace Tests\Feature;

use App\Models\Experience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExperienceCardTest extends TestCase
{
    use RefreshDatabase;

    private function renderCard(Experience $experience): string
    {
        return Blade::render(
            '<x-em.experience-card :experience="$experience" />',
            ['experience' => $experience],
        );
    }

    public function test_translated_story_renders_the_toggle_pill(): void
    {
        Queue::fake();
        App::setLocale('en');

        $experience = Experience::factory()->create([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
            'source_locale' => 'nl',
        ]);
        $experience->forceFill([
            'description_translations' => ['en' => '[en] Beschrijving'],
        ])->saveQuietly();

        $html = $this->renderCard($experience->fresh());

        // The toggle pill and its accessible label are present...
        $this->assertStringContainsString('em-exp__flag', $html);
        $this->assertStringContainsString(__('map.translation_toggle_aria'), $html);
        $this->assertStringContainsString(__('map.translated_show_original'), $html);
        // ...showing the source language's flag, and both description variants
        // for the toggle to swap between.
        $this->assertStringContainsString('🇳🇱', $html);
        $this->assertStringContainsString('[en] Beschrijving', $html);
        $this->assertStringContainsString('Beschrijving', $html);
    }

    public function test_the_title_is_identical_in_every_locale_and_never_toggles(): void
    {
        Queue::fake();

        $experience = Experience::factory()->create([
            'title' => 'Een rustig bankje',
            'description' => 'Mijn favoriete plek bij het water.',
            'source_locale' => 'nl',
        ]);
        $experience->forceFill([
            'description_translations' => ['en' => 'My favourite spot by the water.'],
        ])->saveQuietly();

        App::setLocale('nl');
        $dutch = $this->renderCard($experience->fresh());

        App::setLocale('en');
        $english = $this->renderCard($experience->fresh());

        // Same title either way, as the author wrote it.
        $this->assertStringContainsString('Een rustig bankje', $dutch);
        $this->assertStringContainsString('Een rustig bankje', $english);

        // Only the description participates in the toggle; the title is plain
        // text with no Alpine binding.
        $this->assertStringContainsString('<h3 class="em-exp__title">Een rustig bankje</h3>', $english);
        $this->assertStringContainsString('My favourite spot by the water.', $english);
        $this->assertStringContainsString('Mijn favoriete plek bij het water.', $english);
    }

    public function test_untranslated_story_renders_no_pill(): void
    {
        Queue::fake();
        App::setLocale('nl');

        // Authored in the locale being viewed -> nothing was translated.
        $experience = Experience::factory()->create([
            'title' => 'Origineel',
            'source_locale' => 'nl',
        ]);

        $html = $this->renderCard($experience->fresh());

        $this->assertStringNotContainsString('em-exp__flag', $html);
        $this->assertStringNotContainsString(__('map.translation_toggle_aria'), $html);
    }

    public function test_card_always_renders_a_short_media_strip(): void
    {
        Queue::fake();

        $withoutPhoto = Experience::factory()->create(['photo_path' => null]);
        $withoutHtml = $this->renderCard($withoutPhoto->fresh());

        $this->assertStringContainsString('em-exp__media', $withoutHtml);
        $this->assertStringContainsString('em-exp__media--empty', $withoutHtml);

        $withPhoto = Experience::factory()->withPhoto()->create();
        $withHtml = $this->renderCard($withPhoto->fresh());

        $this->assertStringContainsString('em-exp__media', $withHtml);
        $this->assertStringNotContainsString('em-exp__media--empty', $withHtml);
        $this->assertStringContainsString('<img', $withHtml);
        $this->assertNotNull($withPhoto->fresh()->photo_url);
    }
}
