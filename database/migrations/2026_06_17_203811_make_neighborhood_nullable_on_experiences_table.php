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
            $table->string('neighborhood')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Coerce existing NULLs to a placeholder first, since rows created
        // after this migration ran can legitimately have no resolved wijk.
        DB::table('experiences')->whereNull('neighborhood')->update(['neighborhood' => 'unknown']);

        Schema::table('experiences', function (Blueprint $table) {
            $table->string('neighborhood')->nullable(false)->change();
        });
    }
};
