<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Experience;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExperienceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_submit_page_renders(): void
    {
        $response = $this->get(route('submit'));

        $response->assertStatus(200);
        $response->assertSeeLivewire('submit-form');
        $response->assertSee('dropzone', false);
        $response->assertSee('dropzone__input', false);
        $response->assertSee(__('submit.drop_hint'), false);
    }

    public function test_valid_submission_creates_pending_experience(): void
    {
        $category = Category::where('slug', 'water')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'De Karpendonkse Plas')
            ->set('description', 'Een prachtig meer in het zuiden van Eindhoven.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4621)
            ->set('longitude', 5.5012)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas(Experience::class, [
            'title' => 'De Karpendonkse Plas',
            'category_id' => $category->id,
            'status' => 'pending',
        ]);
    }

    public function test_title_is_required(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', '')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->call('submit')
            ->assertHasErrors(['title' => 'required']);
    }

    public function test_description_is_required(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Test')
            ->set('description', '')
            ->set('category', (string) $category->id)
            ->call('submit')
            ->assertHasErrors(['description' => 'required']);
    }

    public function test_category_must_be_valid(): void
    {
        Livewire::test('submit-form')
            ->set('title', 'Test')
            ->set('description', 'Een beschrijving.')
            ->set('category', '999999')
            ->call('submit')
            ->assertHasErrors(['category' => 'in']);
    }

    public function test_other_category_requires_suggestion(): void
    {
        Livewire::test('submit-form')
            ->set('title', 'Test')
            ->set('description', 'Een beschrijving.')
            ->set('category', 'other')
            ->set('categorySuggestion', '')
            ->call('submit')
            ->assertHasErrors(['categorySuggestion' => 'required_if']);
    }

    public function test_other_category_with_suggestion_creates_experience_without_category(): void
    {
        Livewire::test('submit-form')
            ->set('title', 'Boerenmarkt')
            ->set('description', 'Een gezellige markt op het plein.')
            ->set('category', 'other')
            ->set('categorySuggestion', 'Markten')
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas(Experience::class, [
            'title' => 'Boerenmarkt',
            'category_id' => null,
            'category_suggestion' => 'Markten',
            'status' => 'pending',
        ]);
    }

    public function test_photo_must_be_image(): void
    {
        Storage::fake('public');
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Test')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('photo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
            ->call('submit')
            ->assertHasErrors(['photo' => 'image']);
    }

    public function test_non_image_bytes_renamed_to_jpg_are_rejected(): void
    {
        // The image rule trusts the client extension for Livewire temporary
        // uploads, so the dimensions rule provides the content-based check:
        // a real decode fails for anything that is not an actual image.
        Storage::fake('public');
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Nep foto')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('photo', UploadedFile::fake()->createWithContent('nep.jpg', str_repeat('geen afbeelding', 100)))
            ->call('submit')
            ->assertHasErrors(['photo' => 'dimensions']);

        $this->assertDatabaseCount(Experience::class, 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_photo_with_excessive_pixel_dimensions_is_rejected(): void
    {
        // A small but hugely-dimensioned image (decompression bomb) would be
        // decoded by every visitor's browser once shown on the map, so the
        // dimension caps reject it regardless of its byte size.
        Storage::fake('public');
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Pixelbom')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('photo', UploadedFile::fake()->image('bom.jpg', 10001, 200))
            ->call('submit')
            ->assertHasErrors(['photo' => 'dimensions']);

        $this->assertDatabaseCount(Experience::class, 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_photo_over_4mb_is_rejected_and_never_stored(): void
    {
        // AC6 (issue #77): the browser pipeline guarantees <= 3.5 MB, so any
        // larger upload bypassed the JavaScript and must be rejected.
        Storage::fake('public');
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Te grote foto')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('photo', UploadedFile::fake()->image('groot.jpg')->size(5000))
            ->call('submit')
            ->assertHasErrors(['photo' => 'max']);

        $this->assertDatabaseCount(Experience::class, 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_photo_at_top_of_browser_budget_is_accepted(): void
    {
        // AC7 (issue #77): a browser-compressed photo at the top of its
        // 3.5 MB budget must never be rejected by the server-side rule.
        Storage::fake('public');
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Net onder het budget')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('photo', UploadedFile::fake()->image('gecomprimeerd.jpg')->size(3584))
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $experience = Experience::firstOrFail();
        $this->assertNotNull($experience->photo_path);
        Storage::disk('public')->assertExists($experience->photo_path);
    }

    public function test_remove_photo_clears_photo_and_its_errors(): void
    {
        Storage::fake('public');

        Livewire::test('submit-form')
            ->set('photo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
            ->call('submit')
            ->assertHasErrors(['photo' => 'image'])
            ->call('removePhoto')
            ->assertSet('photo', null)
            ->assertHasNoErrors('photo');
    }

    public function test_photo_upload_stores_file(): void
    {
        Storage::fake('public');
        $category = Category::where('slug', 'cultuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Strijp-S Foto')
            ->set('description', 'Een foto van Strijp-S.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4490)
            ->set('longitude', 5.4550)
            ->set('photo', UploadedFile::fake()->image('strijp.jpg'))
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $experience = Experience::first();
        $this->assertNotNull($experience->photo_path);
        Storage::disk('public')->assertExists($experience->photo_path);
    }

    public function test_uploaded_photo_is_stored_on_public_disk_and_photo_url_resolves(): void
    {
        Storage::fake('public');
        $category = Category::where('slug', 'cultuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Philips Stadion')
            ->set('description', 'Een foto van het stadion.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('photo', UploadedFile::fake()->image('stadion.jpg'))
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $experience = Experience::firstOrFail();

        // AC1: the uploaded file exists on the public disk at the stored path.
        $this->assertNotNull($experience->photo_path);
        Storage::disk('public')->assertExists($experience->photo_path);

        // AC4: photo_url resolves to a non-null, well-formed URL that points at
        // the public storage path, regardless of the configured default disk.
        // Note: Storage::fake('public') serves URLs as root-relative paths
        // (e.g. "/storage/..."); in production the public disk's url is derived
        // from APP_URL and is therefore absolute.
        $photoUrl = $experience->photo_url;
        $this->assertNotNull($photoUrl);
        $this->assertStringContainsString('/storage/'.$experience->photo_path, $photoUrl);
        $this->assertMatchesRegularExpression(
            '#^(https?://[^/]+)?/storage/'.preg_quote($experience->photo_path, '#').'$#',
            $photoUrl,
        );
    }

    public function test_photo_url_uses_public_disk_even_when_default_disk_differs(): void
    {
        // Regression for issue #45: photo_url must resolve via the public disk
        // even if FILESYSTEM_DISK (the default disk) points at "local".
        config(['filesystems.default' => 'local']);
        Storage::fake('public');

        $experience = Experience::factory()->create([
            'photo_path' => 'experiences/test.jpg',
        ]);

        $photoUrl = $experience->photo_url;
        $this->assertNotNull($photoUrl);
        $this->assertStringContainsString('/storage/experiences/test.jpg', $photoUrl);
    }

    public function test_submitted_flag_is_true_on_success(): void
    {
        $category = Category::where('slug', 'verhalen')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Nieuwe Ervaring')
            ->set('description', 'Een mooie plek.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('acceptedTerms', true)
            ->assertSet('submitted', false)
            ->call('submit')
            ->assertSet('submitted', true);
    }

    public function test_location_is_required(): void
    {
        // AC1 (issue #79): a missing pin produces exactly one "location"
        // error, worded around the control the visitor actually sees.
        $category = Category::where('slug', 'natuur')->firstOrFail();

        $component = Livewire::test('submit-form')
            ->set('title', 'Zonder locatie')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertHasErrors(['location'])
            ->assertHasNoErrors(['latitude', 'longitude']);

        $this->assertSame(
            [__('submit.location_required')],
            $component->errors()->all(),
        );
    }

    public function test_location_missing_one_coordinate_reports_single_error(): void
    {
        // Edge case (issue #79): a tampered request or half-populated state
        // that only supplies one coordinate must still surface one error.
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Halve locatie')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->call('submit')
            ->assertHasErrors(['location'])
            ->assertHasNoErrors(['latitude', 'longitude']);
    }

    public function test_location_outside_eindhoven_is_rejected(): void
    {
        // AC2 (issue #79): out-of-bounds coordinates produce exactly one
        // "location" error, not one per coordinate.
        $category = Category::where('slug', 'natuur')->firstOrFail();

        // Amsterdam coordinates — well outside the Eindhoven bounding box.
        $component = Livewire::test('submit-form')
            ->set('title', 'Buiten Eindhoven')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 52.3676)
            ->set('longitude', 4.9041)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertHasErrors(['location'])
            ->assertHasNoErrors(['latitude', 'longitude']);

        $this->assertSame(
            [__('submit.location_out_of_bounds')],
            $component->errors()->all(),
        );
    }

    public function test_location_with_one_coordinate_out_of_bounds_is_rejected(): void
    {
        // Edge case (issue #79): latitude valid, longitude out of bounds —
        // still a single, out-of-bounds-worded error.
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Randgeval')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 4.9041)
            ->call('submit')
            ->assertHasErrors(['location'])
            ->assertHasNoErrors(['latitude', 'longitude']);
    }

    public function test_location_error_message_is_translated(): void
    {
        // AC3 (issue #79): the location error is sourced from lang/{locale}/submit.php,
        // in whichever locale is active when the form is submitted.
        $category = Category::where('slug', 'natuur')->firstOrFail();

        app()->setLocale('nl');

        $component = Livewire::test('submit-form')
            ->set('title', 'Vertaalde fout')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->call('submit');

        $this->assertSame(__('submit.location_required', [], 'nl'), $component->errors()->first('location'));
        $this->assertSame('Kies een locatie op de kaart.', $component->errors()->first('location'));

        app()->setLocale('en');
    }

    public function test_submission_stores_coordinates(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Met coordinaten')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4500)
            ->set('longitude', 5.5000)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $experience = Experience::firstOrFail();
        $this->assertEqualsWithDelta(51.4500, (float) $experience->latitude, 0.0001);
        $this->assertEqualsWithDelta(5.5000, (float) $experience->longitude, 0.0001);
    }

    public function test_submission_determines_neighborhood_from_pin_location(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'In Wijk Centrum')
            ->set('description', 'Een plek in het centrum.')
            ->set('category', (string) $category->id)
            // Coordinate verified to fall inside Wijk Centrum's polygon.
            ->set('latitude', 51.4407636157305)
            ->set('longitude', 5.482401879910195)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas(Experience::class, [
            'title' => 'In Wijk Centrum',
            'neighborhood' => 'Wijk Centrum',
        ]);
    }

    public function test_submission_with_no_matching_wijk_stores_null_neighborhood(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Geen wijk match')
            ->set('description', 'Binnen de gemeentegrens maar buiten alle wijken.')
            ->set('category', (string) $category->id)
            // Inside the Eindhoven bounding box but outside all 20 wijk polygons.
            ->set('latitude', 51.385)
            ->set('longitude', 5.385)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $experience = Experience::where('title', 'Geen wijk match')->firstOrFail();
        $this->assertNull($experience->neighborhood);
    }

    public function test_unaccepted_terms_blocks_submission(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Zonder akkoord')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->call('submit')
            ->assertHasErrors(['acceptedTerms' => 'accepted']);

        $this->assertDatabaseCount(Experience::class, 0);
    }

    public function test_accepted_terms_is_recorded_on_successful_submission(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Met akkoord')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $experience = Experience::firstOrFail();
        $this->assertNotNull($experience->terms_accepted_at);
    }

    public function test_terms_checkbox_label_is_translated_in_dutch(): void
    {
        $response = $this->withSession(['locale' => 'nl'])->get(route('submit'));

        $response->assertOk();
        $response->assertSeeText(__('submit.terms_prefix', locale: 'nl'));
        $response->assertSeeText(__('submit.terms_link_text', locale: 'nl'));
        $response->assertDontSeeText(__('submit.terms_prefix', locale: 'en'));
    }

    public function test_form_warns_that_accepted_submissions_are_published_publicly(): void
    {
        Livewire::test('submit-form')
            ->assertSee(__('submit.privacy_title'))
            ->assertSee(__('submit.privacy_public'));
    }

    public function test_form_states_that_the_email_address_stays_private(): void
    {
        Livewire::test('submit-form')
            ->assertSee(__('submit.privacy_email'))
            ->assertSee(__('submit.email_hint'));
    }

    public function test_privacy_notice_is_translated_in_dutch(): void
    {
        /** @var array<string, string> $dutch */
        $dutch = require lang_path('nl/submit.php');
        /** @var array<string, string> $english */
        $english = require lang_path('en/submit.php');

        $this->app->setLocale('nl');

        // Asserting the English copy is absent is the point: a missing key in
        // lang/nl silently falls back to English, so only comparing against the
        // Dutch strings would still pass on a half-translated release.
        Livewire::test('submit-form')
            ->assertSee($dutch['privacy_public'])
            ->assertSee($dutch['privacy_email'])
            ->assertSee($dutch['email_hint'])
            ->assertDontSee($english['privacy_public'])
            ->assertDontSee($english['privacy_email'])
            ->assertDontSee($english['email_hint']);
    }

    public function test_invalid_email_is_flagged_to_screen_readers(): void
    {
        $component = Livewire::test('submit-form');

        // The terms checkbox always renders aria-invalid="false", so this
        // asserts on the flagged state rather than the attribute's presence.
        $component->assertDontSee('aria-invalid="true"', false);

        $component
            ->set('authorEmail', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['authorEmail' => 'email'])
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="sf-email-hint sf-email-error"', false)
            ->assertSee('id="sf-email-error"', false);
    }

    public function test_privacy_notice_is_not_shown_on_the_success_screen(): void
    {
        $category = Category::where('slug', 'natuur')->firstOrFail();

        Livewire::test('submit-form')
            ->set('title', 'Een plek')
            ->set('description', 'Een beschrijving.')
            ->set('category', (string) $category->id)
            ->set('latitude', 51.4416)
            ->set('longitude', 5.4697)
            ->set('acceptedTerms', true)
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertDontSee(__('submit.privacy_public'))
            ->assertSee(__('submit.success_message'));
    }
}
