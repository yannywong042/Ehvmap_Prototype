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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('color');
            $table->timestamps();
        });

        DB::table('categories')->insert([
            ['slug' => 'natuur', 'color' => '#1B9C84', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'water', 'color' => '#2D72D9', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'cultuur', 'color' => '#8A4FD0', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'verhalen', 'color' => '#E0457B', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'eten', 'color' => '#F2A93B', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
