<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }

    public function test_https_is_not_forced_in_local_environment(): void
    {
        $this->rebootProviderInEnvironment('local');

        $this->assertStringStartsWith('http://', url('/'));
    }

    public function test_https_is_forced_in_production_environment(): void
    {
        $this->rebootProviderInEnvironment('production');

        $this->assertStringStartsWith('https://', url('/'));
    }

    public function test_https_is_forced_in_staging_environment(): void
    {
        $this->rebootProviderInEnvironment('staging');

        $this->assertStringStartsWith('https://', url('/'));
    }

    /**
     * Clear the scheme forced during application bootstrap, then re-run the
     * provider's boot logic as if the app had started in the given environment.
     */
    protected function rebootProviderInEnvironment(string $environment): void
    {
        URL::forceScheme(null);

        $this->app['env'] = $environment;

        (new AppServiceProvider($this->app))->boot();
    }
}
