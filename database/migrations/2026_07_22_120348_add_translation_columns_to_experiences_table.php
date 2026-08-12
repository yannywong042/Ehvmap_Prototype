<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-locale translation columns for a story's title and description,
     * mirroring the `name_translations` JSON column on `categories`. The
     * original submitted text stays in `title`/`description`; these columns
     * hold additive machine translations keyed by locale (e.g. {"en": "..."}).
     */
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->json('title_translations')->nullable()->after('title');
            $table->json('description_translations')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn(['title_translations', 'description_translations']);
        });
    }
};
