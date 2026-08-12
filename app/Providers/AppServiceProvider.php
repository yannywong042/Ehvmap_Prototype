<?php

namespace App\Providers;

use App\Contracts\Translator;
use App\Services\Translation\DeepLTranslator;
use App\Services\Translation\NullTranslator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL; // <-- 1. URL facade toegevoegd
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolve the Translator contract to the driver configured in
        // config/translation.php. Defaults to the NullTranslator passthrough and
        // only uses DeepL once an operator sets DEEPL_API_KEY, so nothing breaks
        // (and no story text leaves the server) without an explicit opt-in.
        $this->app->bind(Translator::class, function (): Translator {
            $useDeepL = config('translation.driver') === 'deepl'
                && filled(config('translation.deepl.key'));

            if ($useDeepL) {
                return new DeepLTranslator(
                    config('translation.deepl.key'),
                    config('translation.deepl.url'),
                );
            }

            return new NullTranslator;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // 2. Forceer HTTPS buiten local (lost de Mixed Content errors op achter de proxy,
        // maar laat `php artisan serve` lokaal assets over http laden)
        if (! app()->environment('local')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
