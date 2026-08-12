<?php

namespace Tests\Feature\Filament;

use App\Contracts\Translator;
use App\Filament\Resources\Experiences\Pages\CreateExperience;
use App\Filament\Resources\Experiences\Pages\EditExperience;
use App\Filament\Resources\Experiences\Pages\ListExperiences;
use App\Jobs\TranslateExperienceJob;
use App\Models\Category;
use App\Models\Experience;
use App\Models\Neighbourhood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeTranslator;
use Tests\TestCase;

class ExperienceResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_admin_can_see_experiences_in_list(): void
    {
        $this->actingAsAdmin();

        $pending = Experience::factory()->pending()->count(3)->create();
        $approved = Experience::factory()->approved()->count(2)->create();

        Livewire::test(ListExperiences::class)
            ->assertCanSeeTableRecords($pending)
            ->assertCanSeeTableRecords($approved);
    }

    public function test_approve_action_sets_status_to_approved(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->pending()->create();

        Livewire::test(ListExperiences::class)
            ->callTableAction('approve', $experience);

        $this->assertDatabaseHas(Experience::class, [
            'id' => $experience->id,
            'status' => 'approved',
        ]);
    }

    public function test_approve_action_without_category_shows_notification_and_does_not_approve(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->pending()->create(['category_id' => null]);

        Livewire::test(ListExperiences::class)
            ->callTableAction('approve', $experience)
            ->assertNotified();

        $this->assertDatabaseHas(Experience::class, [
            'id' => $experience->id,
            'status' => 'pending',
        ]);
    }

    public function test_reject_action_sets_status_to_rejected(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->pending()->create();

        Livewire::test(ListExperiences::class)
            ->callTableAction('reject', $experience);

        $this->assertDatabaseHas(Experience::class, [
            'id' => $experience->id,
            'status' => 'rejected',
        ]);
    }

    public function test_bulk_translate_action_dispatches_a_job_per_selected_experience(): void
    {
        $this->actingAsAdmin();
        Queue::fake();

        $records = Experience::factory()->count(3)->create(['source_locale' => 'nl']);

        Livewire::test(ListExperiences::class)
            ->callTableBulkAction('translate', $records);

        Queue::assertPushed(TranslateExperienceJob::class, 3);

        foreach ($records as $record) {
            Queue::assertPushed(
                TranslateExperienceJob::class,
                fn (TranslateExperienceJob $job): bool => $job->experience->is($record)
                    && $job->sourceLocale === 'nl',
            );
        }
    }

    public function test_bulk_translate_action_backfills_a_missing_source_locale(): void
    {
        $this->actingAsAdmin();
        Queue::fake();

        // A legacy row with no source locale should be given the fallback locale
        // before its translation job is dispatched. The creating hook fills a
        // null on create, so null it out afterwards to mimic a pre-feature row.
        $record = Experience::factory()->create();
        $record->forceFill(['source_locale' => null])->saveQuietly();
        $this->assertNull($record->fresh()->source_locale);

        Livewire::test(ListExperiences::class)
            ->callTableBulkAction('translate', [$record]);

        $this->assertSame(config('app.fallback_locale'), $record->fresh()->source_locale);
        Queue::assertPushed(
            TranslateExperienceJob::class,
            fn (TranslateExperienceJob $job): bool => $job->experience->is($record)
                && $job->sourceLocale === config('app.fallback_locale'),
        );
    }

    public function test_edit_page_loads_for_experience(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->create();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertSuccessful();
    }

    public function test_approving_without_category_shows_validation_error(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->pending()->create(['category_id' => null]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm([
                'status' => 'approved',
                'category_id' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['category_id' => 'required']);
    }

    public function test_admin_can_assign_category_to_experience(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->create(['category_id' => null]);
        $category = Category::factory()->create();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm(['category_id' => $category->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_admin_can_adjust_pin_coordinates(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->create([
            'latitude' => 51.4416,
            'longitude' => 5.4697,
        ]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm([
                'latitude' => 51.4500,
                'longitude' => 5.4800,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'latitude' => 51.4500,
            'longitude' => 5.4800,
        ]);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->create();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm([
                'latitude' => 999,
                'longitude' => 5.4697,
            ])
            ->call('save')
            ->assertHasFormErrors(['latitude']);
    }

    public function test_submitter_fields_are_not_editable_by_admin(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->create();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertFormFieldExists('author_email', fn ($field): bool => $field->isDisabled())
            ->assertFormFieldExists('wants_events', fn ($field): bool => $field->isDisabled());
    }

    public function test_location_map_field_is_present_on_edit_form(): void
    {
        $this->actingAsAdmin();

        $experience = Experience::factory()->create();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertFormFieldExists('location');
    }

    public function test_category_suggestion_field_is_only_visible_when_present(): void
    {
        $this->actingAsAdmin();

        $withSuggestion = Experience::factory()->create([
            'category_id' => null,
            'category_suggestion' => 'Boerenmarkt',
        ]);

        $withoutSuggestion = Experience::factory()->create([
            'category_suggestion' => null,
        ]);

        Livewire::test(EditExperience::class, ['record' => $withSuggestion->id])
            ->assertFormSet(['category_suggestion' => 'Boerenmarkt'])
            ->assertFormFieldExists('category_suggestion', fn ($field) => $field->isVisible());

        Livewire::test(EditExperience::class, ['record' => $withoutSuggestion->id])
            ->assertFormFieldDoesNotExist('category_suggestion');
    }

    public function test_neighborhood_field_is_prefilled_and_editable_by_admin(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);

        $experience = Experience::factory()->create(['neighborhood' => 'Wijk Centrum']);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertFormSet(['neighborhood' => 'Wijk Centrum'])
            ->fillForm(['neighborhood' => 'Wijk Oud-Woensel'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'neighborhood' => 'Wijk Oud-Woensel',
        ]);
    }

    public function test_neighborhood_field_offers_all_wijken_as_dropdown_options(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);

        $experience = Experience::factory()->create(['neighborhood' => 'Wijk Centrum']);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertFormFieldExists('neighborhood', function ($field): bool {
                $options = $field->getOptions();

                return isset($options['Wijk Centrum'], $options['Wijk Oud-Woensel']);
            });
    }

    public function test_legacy_neighborhood_value_remains_editable_without_validation_errors(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);

        $experience = Experience::factory()->create(['neighborhood' => 'Strijp-S']);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertFormSet(['neighborhood' => 'Strijp-S'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'neighborhood' => 'Strijp-S',
        ]);
    }

    public function test_edit_form_warns_when_selected_wijk_does_not_match_map_pin(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);

        $experience = Experience::factory()->create([
            'neighborhood' => 'Wijk Centrum',
            'latitude' => 51.4407636157305,
            'longitude' => 5.482401879910195,
        ]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertDontSee('komt niet overeen')
            ->fillForm(['neighborhood' => 'Wijk Oud-Woensel'])
            ->assertSee('komt niet overeen met de wijk van de pin op de kaart (Wijk Centrum)');
    }

    public function test_create_form_warns_when_selected_wijk_does_not_match_map_pin(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);

        Livewire::test(CreateExperience::class)
            ->fillForm([
                'latitude' => 51.4407636157305,
                'longitude' => 5.482401879910195,
                'neighborhood' => 'Wijk Oud-Woensel',
            ])
            ->assertSee('komt niet overeen met de wijk van de pin op de kaart (Wijk Centrum)')
            ->fillForm(['neighborhood' => 'Wijk Centrum'])
            ->assertDontSee('komt niet overeen');
    }

    public function test_create_form_does_not_warn_when_no_wijk_is_selected_yet(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);

        Livewire::test(CreateExperience::class)
            ->fillForm([
                'latitude' => 51.4407636157305,
                'longitude' => 5.482401879910195,
                'neighborhood' => null,
            ])
            ->assertDontSee('komt niet overeen');
    }

    public function test_moving_the_pin_automatically_updates_the_selected_wijk_on_edit_form(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);

        $experience = Experience::factory()->create([
            'neighborhood' => 'Wijk Oud-Woensel',
            'latitude' => 51.4621,
            'longitude' => 5.5012,
        ]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->set('data.latitude', 51.4407636157305)
            ->set('data.longitude', 5.482401879910195)
            ->assertFormSet(['neighborhood' => 'Wijk Centrum']);
    }

    public function test_moving_the_pin_automatically_selects_the_wijk_on_create_form(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);

        Livewire::test(CreateExperience::class)
            ->set('data.latitude', 51.4407636157305)
            ->set('data.longitude', 5.482401879910195)
            ->assertFormSet(['neighborhood' => 'Wijk Centrum']);
    }

    public function test_moving_the_pin_outside_any_wijk_polygon_does_not_change_the_selected_wijk(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);

        $experience = Experience::factory()->create([
            'neighborhood' => 'Wijk Centrum',
            'latitude' => 51.4407636157305,
            'longitude' => 5.482401879910195,
        ]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->set('data.latitude', 51.385)
            ->set('data.longitude', 5.385)
            ->assertFormSet(['neighborhood' => 'Wijk Centrum']);
    }

    public function test_saving_without_moving_the_pin_does_not_change_the_wijk(): void
    {
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Oud-Woensel']);

        // The pin sits in Wijk Centrum, but the admin has manually overridden
        // the wijk to Wijk Oud-Woensel. Saving without touching the pin must
        // persist that manual choice, not silently recompute it from the pin.
        $experience = Experience::factory()->create([
            'neighborhood' => 'Wijk Oud-Woensel',
            'latitude' => 51.4407636157305,
            'longitude' => 5.482401879910195,
        ]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertFormSet(['neighborhood' => 'Wijk Oud-Woensel'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'neighborhood' => 'Wijk Oud-Woensel',
        ]);
    }

    public function test_admin_can_upload_photo_when_creating_experience(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        $category = Category::factory()->create();

        Livewire::test(CreateExperience::class)
            ->fillForm([
                'title' => 'Met foto',
                'description' => 'Een ervaring met een foto.',
                'category_id' => $category->id,
                'neighborhood' => 'Wijk Centrum',
                'status' => 'approved',
                'latitude' => 51.4416,
                'longitude' => 5.4697,
                'photo_path' => UploadedFile::fake()->image('admin-photo.jpg'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $experience = Experience::where('title', 'Met foto')->firstOrFail();

        $this->assertNotNull($experience->photo_path);
        $this->assertStringStartsWith('experiences/', $experience->photo_path);
        Storage::disk('public')->assertExists($experience->photo_path);

        $this->assertSame(
            Storage::disk('public')->url($experience->photo_path),
            $experience->photo_url,
        );
    }

    public function test_admin_can_upload_photo_when_editing_experience(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $experience = Experience::factory()->create(['photo_path' => null]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm([
                'photo_path' => UploadedFile::fake()->image('edited-photo.jpg'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $experience->refresh();

        $this->assertNotNull($experience->photo_path);
        $this->assertStringStartsWith('experiences/', $experience->photo_path);
        Storage::disk('public')->assertExists($experience->photo_path);
    }

    public function test_replacing_an_existing_photo_removes_the_old_file(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $oldPath = Storage::disk('public')->putFile(
            'experiences',
            UploadedFile::fake()->image('old-photo.jpg'),
        );

        $newPath = Storage::disk('public')->putFile(
            'experiences',
            UploadedFile::fake()->image('new-photo.jpg'),
        );

        $experience = Experience::factory()->create(['photo_path' => $oldPath]);

        // Filament stores the FileUpload state as a keyed array of paths. Swapping
        // the stored path under that key mirrors the admin replacing the photo and
        // saving — the new file must stick while the old one is cleaned up.
        $component = Livewire::test(EditExperience::class, ['record' => $experience->id]);
        $fileKey = array_key_first($component->get('data.photo_path'));

        $component
            ->set('data.photo_path', [$fileKey => $newPath])
            ->call('save')
            ->assertHasNoFormErrors();

        $experience->refresh();

        $this->assertSame($newPath, $experience->photo_path);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_removing_a_photo_nulls_out_the_path_and_deletes_the_file(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $path = Storage::disk('public')->putFile(
            'experiences',
            UploadedFile::fake()->image('to-remove.jpg'),
        );

        $experience = Experience::factory()->create(['photo_path' => $path]);

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm([
                'photo_path' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $experience->refresh();

        $this->assertNull($experience->photo_path);
        $this->assertNull($experience->photo_url);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_create_does_not_dispatch_a_translation_job(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        Neighbourhood::factory()->create(['wijknaam' => 'Wijk Centrum']);
        $category = Category::factory()->create();

        Livewire::test(CreateExperience::class)
            ->fillForm([
                'title' => 'Beheerderstitel',
                'description' => 'Door de beheerder ingevoerd.',
                'category_id' => $category->id,
                'neighborhood' => 'Wijk Centrum',
                'status' => 'approved',
                'latitude' => 51.4416,
                'longitude' => 5.4697,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // The record was created, but no auto-translation was queued.
        $this->assertDatabaseHas('experiences', ['title' => 'Beheerderstitel']);
        Queue::assertNotPushed(TranslateExperienceJob::class);
    }

    public function test_admin_editing_the_title_does_not_dispatch_a_translation_job(): void
    {
        $experience = Experience::factory()->create(['source_locale' => 'nl']);

        Queue::fake();
        $this->actingAsAdmin();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm(['title' => 'Een nieuwe titel'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'title' => 'Een nieuwe titel',
        ]);
        Queue::assertNotPushed(TranslateExperienceJob::class);
    }

    public function test_auto_translate_action_fills_the_translation_fields_in_form_state(): void
    {
        $this->app->instance(Translator::class, new FakeTranslator);
        App::setLocale('nl');
        $this->actingAsAdmin();

        Livewire::test(CreateExperience::class)
            ->fillForm([
                'title' => 'Mijn titel',
                'description' => 'Mijn beschrijving',
            ])
            ->callAction('autoTranslate')
            // Only the description is translated; there is no title field to fill.
            ->assertFormSet([
                'description_translations.en' => '[en] Mijn beschrijving',
            ])
            ->assertFormFieldDoesNotExist('title_translations.en');
    }

    public function test_admin_edited_translations_are_persisted_on_save(): void
    {
        $experience = Experience::factory()->create(['source_locale' => 'nl']);

        Queue::fake();
        $this->actingAsAdmin();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->fillForm([
                'description_translations.en' => 'Hand-edited description',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $experience->refresh();

        $this->assertSame('Hand-edited description', $experience->description_translations['en']);
    }

    public function test_translation_locale_dropdown_excludes_the_source_locale(): void
    {
        $experience = Experience::factory()->create(['source_locale' => 'nl']);

        $this->actingAsAdmin();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->assertFormFieldExists('translation_locale', function ($field): bool {
                $options = $field->getOptions();

                // The source locale (nl) is not offered; the other locale is.
                return isset($options['en']) && ! isset($options['nl']);
            })
            // The selected locale's editor is shown; the source has no editor.
            ->assertFormFieldExists('description_translations.en')
            ->assertFormFieldDoesNotExist('description_translations.nl')
            // And no locale gets a title input at all — titles are not translated.
            ->assertFormFieldDoesNotExist('title_translations.en');
    }

    public function test_admin_can_correct_the_source_language_and_the_edit_targets_follow(): void
    {
        // A legacy row mislabelled as English (e.g. backfilled to the fallback)
        // even though the text is Dutch.
        $experience = Experience::factory()->create(['source_locale' => 'en']);

        $this->actingAsAdmin();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            // With source = en, the only thing to translate is Dutch.
            ->assertFormFieldExists('translation_locale', fn ($field): bool => isset($field->getOptions()['nl'])
                && ! isset($field->getOptions()['en']))
            // The admin corrects the original language to Dutch.
            ->fillForm(['source_locale' => 'nl'])
            // Now the translatable target flips to English, reactively.
            ->assertFormFieldExists('translation_locale', fn ($field): bool => isset($field->getOptions()['en'])
                && ! isset($field->getOptions()['nl']))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('nl', $experience->fresh()->source_locale);
    }

    public function test_editing_a_translation_keeps_the_other_locales_text_intact(): void
    {
        // Source nl with an existing en translation and the source-language copy.
        $experience = Experience::factory()->create(['source_locale' => 'nl']);
        $experience->forceFill([
            'description_translations' => ['nl' => 'Origineel NL desc', 'en' => 'Old EN desc'],
        ])->saveQuietly();

        $this->actingAsAdmin();

        Livewire::test(EditExperience::class, ['record' => $experience->id])
            ->set('data.description_translations.en', 'Updated EN desc')
            ->call('save')
            ->assertHasNoFormErrors();

        $experience->refresh();

        // The edited locale is updated...
        $this->assertSame('Updated EN desc', $experience->description_translations['en']);
        // ...while the other locale's text (here the source copy) is preserved.
        $this->assertSame('Origineel NL desc', $experience->description_translations['nl']);
    }
}
