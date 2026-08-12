<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\CategoryIcons;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CategoryIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_icon_component_renders_colored_svg_for_each_canonical_slug(): void
    {
        foreach (array_keys(CategoryIcons::PATHS) as $slug) {
            $color = Category::CANONICAL[$slug]['color'];

            $html = Blade::render(
                '<x-em.category-icon :slug="$slug" :color="$color" />',
                ['slug' => $slug, 'color' => $color],
            );

            $this->assertStringContainsString('data-category-icon="'.$slug.'"', $html);
            $this->assertStringContainsString('color: '.$color, $html);
            $this->assertStringContainsString('<path d="', $html);
        }
    }

    public function test_category_filters_render_icons_instead_of_color_dots(): void
    {
        $this->seed(CategorySeeder::class);

        $html = Blade::render(
            '@include("components.map-explorer.category-filters", ["categories" => $categories, "activeCategories" => []])',
            ['categories' => Category::query()->orderBy('id')->get()],
        );

        $this->assertStringNotContainsString('em-cat__dot', $html);
        $this->assertStringContainsString('em-cat__icon', $html);
        $this->assertStringContainsString('data-category-icon="natuur"', $html);
        $this->assertStringContainsString('data-category-icon="eten"', $html);
        $this->assertStringContainsString('#1B9C84', $html);
        $this->assertStringContainsString('#F2A93B', $html);
    }

    public function test_unknown_slug_falls_back_to_natuur_icon(): void
    {
        $this->assertSame('natuur', CategoryIcons::resolveSlug('unknown'));
        $this->assertSame(CategoryIcons::PATHS['natuur'], CategoryIcons::pathsFor('unknown'));
    }
}
