<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('name_translations')->nullable()->after('slug');
        });

        foreach (DB::table('categories')->select('id', 'slug')->get() as $category) {
            $translations = [];

            foreach (['en', 'nl'] as $locale) {
                $translations[$locale] = $this->resolveName($category->slug, $locale);
            }

            DB::table('categories')
                ->where('id', $category->id)
                ->update(['name_translations' => json_encode($translations)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name_translations');
        });
    }

    /**
     * Translations previously lived in lang/{locale}/categories.php. Read those
     * files when they still exist (locally edited installs), otherwise fall back
     * to the shipped defaults, and finally to the slug itself.
     */
    private function resolveName(string $slug, string $locale): string
    {
        $defaults = [
            'en' => [
                'natuur' => 'Nature',
                'water' => 'Water',
                'cultuur' => 'Culture',
                'verhalen' => 'Stories',
                'eten' => 'Food & Drink',
            ],
            'nl' => [
                'natuur' => 'Natuur',
                'water' => 'Water',
                'cultuur' => 'Cultuur',
                'verhalen' => 'Verhalen',
                'eten' => 'Eten & Drinken',
            ],
        ];

        $path = lang_path("{$locale}/categories.php");

        $fromFile = file_exists($path) ? (require $path)[$slug] ?? null : null;

        return $fromFile ?? $defaults[$locale][$slug] ?? $slug;
    }
};
