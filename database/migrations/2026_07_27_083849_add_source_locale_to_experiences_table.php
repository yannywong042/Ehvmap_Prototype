<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the locale a story's original title/description were authored in, so
     * the UI can tell when it is showing a translation and offer to reveal the
     * original. Pre-existing rows are assumed to be in the app fallback locale.
     */
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->string('source_locale', 5)->nullable()->after('description_translations');
        });

        DB::table('experiences')
            ->whereNull('source_locale')
            ->update(['source_locale' => config('app.fallback_locale', 'en')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn('source_locale');
        });
    }
};
