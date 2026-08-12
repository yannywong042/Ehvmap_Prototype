<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Experience;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_list_shows_seeded_categories(): void
    {
        $this->actingAsAdmin();

        $categories = Category::factory()->count(3)->create();

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords($categories);
    }

    public function test_create_writes_slug_and_translations(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name_translations.en' => 'Markets',
                'name_translations.nl' => 'Markten',
                'color' => '#112233',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('slug', 'markten')->firstOrFail();

        $this->assertSame('#112233', $category->color);
        $this->assertSame('Markets', $category->nameForLocale('en'));
        $this->assertSame('Markten', $category->nameForLocale('nl'));
    }

    public function test_duplicate_translation_generates_unique_slug(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name_translations.en' => 'Markets',
                'name_translations.nl' => 'Markten',
                'color' => '#112233',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name_translations.en' => 'Markets Two',
                'name_translations.nl' => 'Markten',
                'color' => '#445566',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', ['slug' => 'markten']);
        $this->assertDatabaseHas('categories', ['slug' => 'markten-2']);
        $this->assertSame('Markets Two', Category::where('slug', 'markten-2')->firstOrFail()->nameForLocale('en'));
    }

    public function test_edit_page_prefills_translations(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create([
            'slug' => 'markten',
            'name_translations' => ['en' => 'Markets', 'nl' => 'Markten'],
            'color' => '#112233',
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->assertFormSet([
                'name_translations.en' => 'Markets',
                'name_translations.nl' => 'Markten',
                'color' => '#112233',
            ]);
    }

    public function test_edit_updates_translations_and_color(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create([
            'slug' => 'markten',
            'name_translations' => ['en' => 'Markets', 'nl' => 'Markten'],
            'color' => '#112233',
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->fillForm([
                'name_translations.en' => 'Market Squares',
                'name_translations.nl' => 'Marktpleinen',
                'color' => '#445566',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();

        $this->assertSame('#445566', $category->color);
        $this->assertSame('markten', $category->slug);
        $this->assertSame('Market Squares', $category->nameForLocale('en'));
        $this->assertSame('Marktpleinen', $category->nameForLocale('nl'));
    }

    public function test_delete_removes_category(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create([
            'slug' => 'markten',
            'name_translations' => ['en' => 'Markets', 'nl' => 'Markten'],
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_with_experiences_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create();
        $experience = Experience::factory()->create(['category_id' => $category->id]);

        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'category_id' => $category->id,
        ]);
    }
}
