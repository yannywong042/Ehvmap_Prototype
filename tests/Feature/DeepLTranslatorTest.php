<?php

namespace Tests\Feature;

use App\Contracts\Translator;
use App\Services\Translation\DeepLTranslator;
use App\Services\Translation\NullTranslator;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepLTranslatorTest extends TestCase
{
    public function test_it_translates_maps_locales_to_deepl_codes_and_sends_the_auth_header(): void
    {
        Http::fake([
            'api-free.deepl.com/*' => Http::response([
                'translations' => [
                    ['detected_source_language' => 'NL', 'text' => 'Hidden gem'],
                ],
            ], 200),
        ]);

        $translator = new DeepLTranslator('secret-key', 'https://api-free.deepl.com');

        $result = $translator->translate('Verborgen parel', 'nl', 'en');

        $this->assertSame('Hidden gem', $result);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/v2/translate')
                && $request->hasHeader('Authorization', 'DeepL-Auth-Key secret-key')
                && $request['text'] === 'Verborgen parel'
                && $request['source_lang'] === 'NL'
                && $request['target_lang'] === 'EN';
        });
    }

    public function test_it_detects_the_source_language_via_auto_detect(): void
    {
        Http::fake([
            'api-free.deepl.com/*' => Http::response([
                'translations' => [
                    ['detected_source_language' => 'EN', 'text' => 'ignored'],
                ],
            ], 200),
        ]);

        $detected = (new DeepLTranslator('secret-key', 'https://api-free.deepl.com'))
            ->detect('My favourite bench by the water.');

        // DeepL's uppercase code is normalised to a lowercase app locale.
        $this->assertSame('en', $detected);

        // Detection auto-detects, so it must NOT pin a source_lang.
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/v2/translate')
                && $request->hasHeader('Authorization', 'DeepL-Auth-Key secret-key')
                && ! array_key_exists('source_lang', $request->data())
                && $request['target_lang'] === 'EN-GB';
        });
    }

    public function test_it_strips_region_suffixes_from_locale_codes(): void
    {
        Http::fake([
            '*' => Http::response(['translations' => [['text' => 'ok']]], 200),
        ]);

        (new DeepLTranslator('secret-key'))->translate('Hallo', 'nl-NL', 'en_US');

        Http::assertSent(fn (Request $request): bool => $request['source_lang'] === 'NL'
            && $request['target_lang'] === 'EN');
    }

    public function test_it_uses_the_pro_base_url_when_configured(): void
    {
        Http::fake([
            'api.deepl.com/*' => Http::response(['translations' => [['text' => 'ok']]], 200),
        ]);

        (new DeepLTranslator('pro-key', 'https://api.deepl.com'))->translate('Hallo', 'nl', 'en');

        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://api.deepl.com/v2/translate',
        ));
    }

    public function test_it_throws_on_http_failure_so_the_job_retries(): void
    {
        // 456 is DeepL's "quota exceeded" — any non-2xx must throw.
        Http::fake([
            '*' => Http::response('Quota exceeded', 456),
        ]);

        $this->expectException(RequestException::class);

        (new DeepLTranslator('secret-key'))->translate('Verborgen parel', 'nl', 'en');
    }

    public function test_it_returns_null_on_an_empty_or_broken_result(): void
    {
        Http::fake([
            '*' => Http::response(['translations' => [['text' => '']]], 200),
        ]);

        $this->assertNull(
            (new DeepLTranslator('secret-key'))->translate('Verborgen parel', 'nl', 'en'),
        );
    }

    public function test_it_returns_null_when_translations_are_missing_entirely(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'unexpected shape'], 200),
        ]);

        $this->assertNull(
            (new DeepLTranslator('secret-key'))->translate('Verborgen parel', 'nl', 'en'),
        );
    }

    public function test_it_does_not_call_the_api_for_blank_input(): void
    {
        Http::fake();

        $this->assertNull((new DeepLTranslator('secret-key'))->translate('   ', 'nl', 'en'));

        Http::assertNothingSent();
    }

    public function test_container_resolves_null_translator_by_default_when_no_key_is_set(): void
    {
        Config::set('translation.driver', 'deepl');
        Config::set('translation.deepl.key', null);

        $this->assertInstanceOf(NullTranslator::class, $this->app->make(Translator::class));
    }

    public function test_container_resolves_deepl_translator_when_driver_and_key_are_set(): void
    {
        Config::set('translation.driver', 'deepl');
        Config::set('translation.deepl.key', 'secret-key');

        $this->assertInstanceOf(DeepLTranslator::class, $this->app->make(Translator::class));
    }
}
