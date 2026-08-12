<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Slugs, colors, and translations come from Category::CANONICAL, the
     * single source of truth also consumed by CategoryFactory::canonical().
     */
    public function run(): void
    {
        foreach (Category::CANONICAL as $slug => $attributes) {
            Category::firstOrCreate(['slug' => $slug], [
                'color' => $attributes['color'],
                'name_translations' => [
                    'en' => $attributes['en'],
                    'nl' => $attributes['nl'],
                ],
            ]);
        }
    }
}
