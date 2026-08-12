<?php

namespace Database\Seeders;

use App\Models\Experience;
use Illuminate\Database\Seeder;

class ExperienceSeeder extends Seeder
{
    /**
     * Total experiences to seed, split ~70/20/10 across approved/pending/
     * rejected so the moderation queue and rejected list are both non-empty.
     */
    private const int TOTAL = 50;

    /**
     * Run the database seeds.
     *
     * Depends explicitly on categories and neighbourhoods existing first
     * (both idempotent, so calling them again here is safe even when this
     * seeder is run on its own).
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            NeighbourhoodSeeder::class,
        ]);

        $pendingCount = (int) round(self::TOTAL * 0.2);
        $rejectedCount = (int) round(self::TOTAL * 0.1);
        $approvedCount = self::TOTAL - $pendingCount - $rejectedCount;

        Experience::factory()->count($approvedCount)->approved()->withPhoto()->create();
        Experience::factory()->count($pendingCount)->pending()->create();
        Experience::factory()->count($rejectedCount)->rejected()->create();
    }
}
