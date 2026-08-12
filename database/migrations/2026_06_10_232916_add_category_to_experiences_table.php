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
        Schema::table('experiences', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->string('category_suggestion')->nullable()->after('category_id');
        });

        $categoryIds = DB::table('categories')->pluck('id', 'slug');

        DB::table('experiences')->select('id', 'category')->orderBy('id')->chunkById(100, function ($experiences) use ($categoryIds) {
            foreach ($experiences as $experience) {
                DB::table('experiences')
                    ->where('id', $experience->id)
                    ->update(['category_id' => $categoryIds[$experience->category] ?? null]);
            }
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            // No reverse data migration: category slugs cannot be reconstructed once dropped.
            $table->string('category')->default('natuur');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('category_suggestion');
        });
    }
};
