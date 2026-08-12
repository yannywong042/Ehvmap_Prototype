<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_is_seeded_with_documented_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->canAccessPanel(Panel::make()->id('admin')));
    }

    public function test_seeding_twice_does_not_throw_on_the_admin_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'test@example.com')->count());
    }
}
