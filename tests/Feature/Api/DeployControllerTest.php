<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeployControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_runs_with_valid_token(): void
    {
        config(['services.deploy.token' => 'secret-token']);

        $response = $this->withHeader('X-Deploy-Token', 'secret-token')
            ->postJson(route('api.deploy.migrate'));

        $response->assertOk();
        $response->assertJsonStructure(['output']);
    }

    public function test_migrate_also_links_public_storage(): void
    {
        config(['services.deploy.token' => 'secret-token']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', ['--force' => true])
            ->andReturn(0);
        Artisan::shouldReceive('call')
            ->once()
            ->with('storage:link', ['--force' => true])
            ->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');

        $response = $this->withHeader('X-Deploy-Token', 'secret-token')
            ->postJson(route('api.deploy.migrate'));

        $response->assertOk();
    }

    public function test_migrate_rejects_missing_token(): void
    {
        config(['services.deploy.token' => 'secret-token']);

        $response = $this->postJson(route('api.deploy.migrate'));

        $response->assertForbidden();
    }

    public function test_migrate_rejects_invalid_token(): void
    {
        config(['services.deploy.token' => 'secret-token']);

        $response = $this->withHeader('X-Deploy-Token', 'wrong-token')
            ->postJson(route('api.deploy.migrate'));

        $response->assertForbidden();
    }

    public function test_migrate_rejects_when_token_not_configured(): void
    {
        config(['services.deploy.token' => null]);

        $response = $this->withHeader('Authorization', 'Bearer anything')
            ->postJson(route('api.deploy.migrate'));

        $response->assertForbidden();
    }
}
