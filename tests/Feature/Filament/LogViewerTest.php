<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\LogViewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create([
            'email' => 'jordan.voets@gmail.com',
        ]));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/log-viewer')->assertRedirect('/admin/login');
    }

    public function test_other_users_cannot_access_the_page(): void
    {
        $this->actingAs(User::factory()->create([
            'email' => 'someone-else@example.com',
        ]));

        $this->get('/admin/log-viewer')->assertForbidden();
    }

    public function test_log_entries_are_listed_newest_first(): void
    {
        $this->actingAsAdmin();

        File::put(storage_path('logs/laravel.log'), implode("\n", [
            '[2024-01-01 10:00:00] local.INFO: First entry',
            '[2024-01-01 11:00:00] local.ERROR: Second entry',
            '#0 Some/Stack/Trace.php(12): something()',
        ]));

        Livewire::test(LogViewer::class)
            ->assertSeeInOrder(['Second entry', 'First entry']);

        File::delete(storage_path('logs/laravel.log'));
    }

    public function test_can_filter_by_level(): void
    {
        $this->actingAsAdmin();

        File::put(storage_path('logs/laravel.log'), implode("\n", [
            '[2024-01-01 10:00:00] local.INFO: An info entry',
            '[2024-01-01 11:00:00] local.ERROR: An error entry',
        ]));

        Livewire::test(LogViewer::class)
            ->assertSee('An info entry')
            ->assertSee('An error entry')
            ->filterTable('level', 'error')
            ->assertSee('An error entry')
            ->assertDontSee('An info entry');

        File::delete(storage_path('logs/laravel.log'));
    }
}
