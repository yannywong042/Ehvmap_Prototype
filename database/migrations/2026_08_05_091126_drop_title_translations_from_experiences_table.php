<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Story titles are no longer machine translated (see issue #106): a title is
     * a short, evocative fragment that loses the author's voice once a provider
     * flattens it, so every locale renders the original submitted text. Only the
     * description is translated, which leaves this column dead weight.
     *
     * Dropping it permanently deletes every stored title translation, including
     * any an admin hand-corrected. That is intentional and irreversible.
     */
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn('title_translations');
        });
    }

    /**
     * Restore the column as an empty nullable JSON map. No data is recovered and
     * nothing repopulates it — the application no longer writes to it.
     */
    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->json('title_translations')->nullable()->after('title');
        });
    }
};
