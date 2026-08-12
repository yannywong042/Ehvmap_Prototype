<?php

namespace Tests\Feature;

use App\Contracts\Translator;
use App\Jobs\TranslateExperienceJob;
use App\Models\Category;
use App\Models\Experience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\FakeTranslator;
use Tests\TestCase;

class StoryTranslationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind the deterministic fake translator in place of any real provider so
     * the pipeline runs without a network call.
     */
    private function fakeTranslator(string $mode = 'prefix'): void
    {
        $this->app->instance(Translator::class, new FakeTranslator($mode));
    }

    /**
     * Create a story the same way the public submit form does — via
     * Experience::create() — so the model's create-time translation hook fires.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function submitStory(array $attributes = []): Experience
    {
        return Experience::create(array_merge([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
            'category_id' => Category::query()->value('id'),
            'status' => 'pending',
        ], $attributes));
    }

    public function test_submitting_a_story_dispatches_the_translation_job_with_the_source_locale(): void
    {
        Queue::fake();
        App::setLocale('nl');

        $experience = $this->submitStory();

        Queue::assertPushed(
            TranslateExperienceJob::class,
            fn (TranslateExperienceJob $job): bool => $job->experience->is($experience)
                && $job->sourceLocale === 'nl',
        );
    }

    public function test_reading_a_story_never_re_translates(): void
    {
        Queue::fake();

        $experience = $this->submitStory();

        // Reading the localized accessors must not enqueue any further work.
        $experience->localized_description;
        $experience->descriptionForLocale('en');
        $experience->fresh()->localized_description;

        Queue::assertPushed(TranslateExperienceJob::class, 1);
    }

    public function test_job_populates_translations_additively_without_losing_the_original(): void
    {
        $this->fakeTranslator();
        App::setLocale('nl');

        // Sync queue: the create-time job runs immediately with the fake translator.
        $experience = $this->submitStory([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
        ])->fresh();

        // Original columns are untouched.
        $this->assertSame('Origineel', $experience->title);
        $this->assertSame('Beschrijving', $experience->description);

        // Source locale keeps the original text verbatim.
        $this->assertSame('Beschrijving', $experience->description_translations['nl']);

        // The other supported locale is machine translated.
        $this->assertSame('[en] Beschrijving', $experience->description_translations['en']);
    }

    public function test_the_title_is_never_sent_to_the_provider_or_translated(): void
    {
        $translator = new FakeTranslator;
        $this->app->instance(Translator::class, $translator);
        App::setLocale('nl');

        $experience = $this->submitStory([
            'title' => 'Een rustig bankje',
            'description' => 'Mijn favoriete plek bij het water.',
        ])->fresh();

        // Only the description ever reaches the provider — not the title, not
        // even as part of the language-detection sample.
        foreach ($translator->translated as $text) {
            $this->assertStringNotContainsString('Een rustig bankje', $text);
        }

        foreach ($translator->detected as $text) {
            $this->assertStringNotContainsString('Een rustig bankje', $text);
        }

        // And the title itself is identical whichever locale you read it in.
        $this->assertSame('Een rustig bankje', $experience->title);

        App::setLocale('en');
        $this->assertSame('Een rustig bankje', $experience->title);
        $this->assertSame('[en] Mijn favoriete plek bij het water.', $experience->localized_description);
    }

    public function test_accessor_returns_translation_and_falls_back_to_original_when_missing(): void
    {
        $experience = Experience::factory()->create([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
        ]);

        $experience->forceFill([
            'description_translations' => ['en' => '[en] Beschrijving'],
        ])->saveQuietly();

        // Present locale resolves to the translation.
        $this->assertSame('[en] Beschrijving', $experience->descriptionForLocale('en'));

        // Missing locale falls back to the original submitted text.
        $this->assertSame('Beschrijving', $experience->descriptionForLocale('nl'));

        // The title is not locale-resolved at all — it is the original text.
        $this->assertSame('Origineel', $experience->title);
    }

    public function test_provider_failure_keeps_the_original_and_does_not_block_publication(): void
    {
        Queue::fake();
        App::setLocale('nl');

        // Publication path: the story is created even though the provider is
        // down; the translation job is queued for later retry.
        $experience = $this->submitStory([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
        ]);

        $this->assertDatabaseHas(Experience::class, [
            'id' => $experience->id,
            'title' => 'Origineel',
        ]);
        Queue::assertPushed(TranslateExperienceJob::class);

        // Running that queued job against a failing provider keeps the original
        // and throws so the queue retries — bad output is never stored.
        $this->expectException(RuntimeException::class);

        try {
            (new TranslateExperienceJob($experience, 'nl'))->handle(new FakeTranslator('throw'));
        } finally {
            $experience->refresh();
            $this->assertSame('Origineel', $experience->title);
            // Source locale entry is still recorded; the failed target locale is not.
            $this->assertArrayNotHasKey('en', $experience->description_translations ?? []);
        }
    }

    public function test_no_translation_available_is_stored_as_null_and_not_retried(): void
    {
        $experience = Experience::factory()->create([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
            'source_locale' => 'nl',
        ]);

        // A provider that returns nothing (an empty/blank response, or the
        // NullTranslator when no provider is configured) is a terminal "no
        // translation available" state — NOT a transient error. The job records
        // an explicit null and completes without throwing for a retry.
        (new TranslateExperienceJob($experience, 'nl'))->handle(new FakeTranslator('empty'));

        $experience->refresh();

        // The unavailable locale is stored as an explicit null.
        $this->assertNull($experience->description_translations['en']);

        // The reader falls back to the original text and the flag stays hidden.
        App::setLocale('en');
        $this->assertSame('Beschrijving', $experience->descriptionForLocale('en'));
        $this->assertFalse($experience->isTranslatedForLocale('en'));
    }

    public function test_null_translator_default_stores_null_without_failing(): void
    {
        $experience = Experience::factory()->create([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
            'source_locale' => 'nl',
        ]);

        // The default container binding (no provider configured) is the
        // NullTranslator; running the job with it must not throw or fake a
        // translation.
        (new TranslateExperienceJob($experience, 'nl'))->handle($this->app->make(Translator::class));

        $experience->refresh();

        $this->assertNull($experience->description_translations['en']);
        App::setLocale('en');
        $this->assertFalse($experience->isTranslatedForLocale('en'));
    }

    public function test_submission_detects_the_real_language_and_translates_into_the_others(): void
    {
        // The ideal end-to-end flow: a Dutch person browsing the site in English
        // (app locale "en") submits Dutch text. The site locale is NOT the
        // language they wrote in, so the system must recognise Dutch, record it
        // as the source, and translate into every other supported locale.
        App::setLocale('en');
        $this->app->instance(Translator::class, new FakeTranslator('prefix', 'nl'));

        $experience = $this->submitStory([
            'title' => 'Een rustig bankje',
            'description' => 'Mijn favoriete plek bij het water.',
        ])->fresh();

        // Source is the DETECTED language, not the visitor's site locale.
        $this->assertSame('nl', $experience->source_locale);
        // The original stays under the detected source; the other locale is
        // translated from it.
        $this->assertSame('Mijn favoriete plek bij het water.', $experience->description_translations['nl']);
        $this->assertSame('[en] Mijn favoriete plek bij het water.', $experience->description_translations['en']);
        // The title is untouched by all of this — same text in every locale.
        $this->assertSame('Een rustig bankje', $experience->title);
        // And the flag treats English as the translation for an English reader.
        App::setLocale('en');
        $this->assertTrue($experience->isTranslatedForLocale('en'));
    }

    public function test_detected_language_overrides_a_wrong_stored_source_and_rebuilds(): void
    {
        // The story is actually English but was recorded as Dutch (the visitor's
        // site locale at submission). A provider that detects "en" must flip the
        // source and rebuild the translations from the real language, rather than
        // translating English-as-Dutch into nonsense.
        $experience = Experience::factory()->create([
            'title' => 'A quiet bench',
            'description' => 'My favourite spot by the water.',
            'source_locale' => 'nl',
            // Stale: nl wrongly held the English original because it was the source.
            'description_translations' => ['nl' => 'My favourite spot by the water.'],
        ]);

        (new TranslateExperienceJob($experience, 'nl'))->handle(new FakeTranslator('prefix', 'en'));

        $experience->refresh();

        // Source is corrected to the detected language.
        $this->assertSame('en', $experience->source_locale);
        // English (the real source) keeps the verbatim original.
        $this->assertSame('My favourite spot by the water.', $experience->description_translations['en']);
        // Dutch is now a real translation FROM English, replacing the stale copy.
        $this->assertSame('[nl] My favourite spot by the water.', $experience->description_translations['nl']);
        // And the flag now treats Dutch as the translation, not the original.
        App::setLocale('nl');
        $this->assertTrue($experience->isTranslatedForLocale('nl'));
    }

    public function test_only_missing_locales_are_translated_leaving_existing_ones_intact(): void
    {
        // Source nl is correct; en was already translated (or hand-corrected).
        $experience = Experience::factory()->create([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
            'source_locale' => 'nl',
            'description_translations' => ['nl' => 'Beschrijving', 'en' => 'Hand-checked description'],
        ]);

        // Detection agrees (nl), so nothing is reset and the present en entry is
        // left untouched — only genuinely missing locales get translated.
        (new TranslateExperienceJob($experience, 'nl'))->handle(new FakeTranslator('prefix', 'nl'));

        $experience->refresh();

        $this->assertSame('Hand-checked description', $experience->description_translations['en']);
    }

    public function test_backfill_command_only_translates_stories_missing_translations(): void
    {
        $this->fakeTranslator();

        // A real, published submission is fully translated on create (both locales present).
        $complete = $this->submitStory(['status' => 'approved'])->fresh();

        // A legacy row that predates the feature: factory-created, no translations.
        $legacy = Experience::factory()->create(['status' => 'approved']);

        Queue::fake();

        $this->artisan('experiences:backfill-translations', ['--from' => 'nl'])
            ->assertSuccessful();

        Queue::assertPushed(
            TranslateExperienceJob::class,
            fn (TranslateExperienceJob $job): bool => $job->experience->is($legacy),
        );
        Queue::assertPushed(TranslateExperienceJob::class, 1);
        $this->assertNotNull($complete->description_translations);
    }

    public function test_backfill_ignores_a_story_whose_descriptions_are_complete(): void
    {
        // Every supported locale has a description translation. Completeness is
        // judged on descriptions alone, so this story is never re-dispatched.
        $experience = Experience::factory()->create([
            'status' => 'approved',
            'source_locale' => 'nl',
            'description_translations' => ['nl' => 'Beschrijving', 'en' => '[en] Beschrijving'],
        ]);

        Queue::fake();

        $this->artisan('experiences:backfill-translations', ['--from' => 'nl'])
            ->assertSuccessful();

        Queue::assertNotPushed(TranslateExperienceJob::class);
        $this->assertNotNull($experience->fresh()->description_translations);
    }

    public function test_editing_the_original_regenerates_translations_but_other_edits_do_not(): void
    {
        Queue::fake();

        $experience = $this->submitStory();
        Queue::assertPushed(TranslateExperienceJob::class, 1); // created

        // Editing an unrelated field does not re-translate.
        $experience->update(['status' => 'approved']);
        Queue::assertPushed(TranslateExperienceJob::class, 1);

        // Editing the original title regenerates translations.
        $experience->update(['title' => 'Nieuwe titel']);
        Queue::assertPushed(TranslateExperienceJob::class, 2);
    }

    public function test_source_locale_is_set_on_create_from_the_active_locale(): void
    {
        Queue::fake();
        App::setLocale('nl');

        $experience = $this->submitStory();

        $this->assertSame('nl', $experience->fresh()->source_locale);
    }

    public function test_is_translated_for_locale_covers_the_three_cases(): void
    {
        Queue::fake();

        $experience = $this->submitStory(['source_locale' => 'nl']);
        $experience->forceFill([
            'description_translations' => ['en' => '[en] Beschrijving'],
        ])->saveQuietly();

        // Different source locale + a translation exists -> translated.
        $this->assertTrue($experience->isTranslatedForLocale('en'));

        // Requested locale is the source locale -> not translated.
        $this->assertFalse($experience->isTranslatedForLocale('nl'));

        // Different source locale but no translation for that locale -> not translated.
        $this->assertFalse($experience->isTranslatedForLocale('de'));
    }

    public function test_backfill_sets_source_locale_on_legacy_rows(): void
    {
        $legacy = Experience::factory()->create(['status' => 'approved']);
        $legacy->forceFill([
            'source_locale' => null,
            'description_translations' => null,
        ])->saveQuietly();

        Queue::fake();

        $this->artisan('experiences:backfill-translations', ['--from' => 'nl'])
            ->assertSuccessful();

        $this->assertSame('nl', $legacy->fresh()->source_locale);
    }

    public function test_to_array_carries_the_fields_the_client_toggle_needs(): void
    {
        Queue::fake();
        App::setLocale('en');

        $experience = $this->submitStory([
            'title' => 'Origineel',
            'description' => 'Beschrijving',
            'source_locale' => 'nl',
        ]);
        $experience->forceFill([
            'description_translations' => ['en' => '[en] Beschrijving'],
        ])->saveQuietly();

        $array = $experience->fresh()->toArray();

        // The title is the original text, and carries no "original_title" twin
        // for the client toggle to swap to.
        $this->assertSame('Origineel', $array['title']);
        $this->assertArrayNotHasKey('original_title', $array);
        $this->assertSame('[en] Beschrijving', $array['description']);
        $this->assertSame('Beschrijving', $array['original_description']);
        $this->assertSame('nl', $array['source_locale']);
        $this->assertTrue($array['is_translated']);
    }

    public function test_to_array_is_not_translated_when_source_matches_locale(): void
    {
        Queue::fake();
        App::setLocale('nl');

        $array = $this->submitStory(['source_locale' => 'nl'])->fresh()->toArray();

        $this->assertFalse($array['is_translated']);
    }
}
