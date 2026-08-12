<?php

namespace App\Services\Translation;

use App\Contracts\Translator;
use Illuminate\Support\Facades\Http;

/**
 * DeepL-backed translation driver.
 *
 * Talks to the DeepL REST API with Laravel's built-in HTTP client — no SDK or
 * extra Composer dependency. Works against both the Free
 * (https://api-free.deepl.com) and Pro (https://api.deepl.com) base URLs,
 * selected via config/translation.php.
 *
 * Failure handling matches TranslateExperienceJob's contract:
 * - HTTP error / non-2xx / timeout -> throw, so the queued job retries later
 *   rather than dropping the story's translation.
 * - Empty or clearly-broken response -> return null, so the caller keeps the
 *   original text instead of overwriting it with bad output.
 *
 * Proper-noun preservation (e.g. "Stratumseind", "Genneper Parken") is
 * best-effort via DeepL's own model. A future enhancement could register a
 * DeepL glossary to pin such terms; that is intentionally out of scope here.
 */
class DeepLTranslator implements Translator
{
    public function __construct(
        private string $apiKey,
        private string $apiUrl = 'https://api-free.deepl.com',
    ) {}

    public function translate(string $text, string $from, string $to): ?string
    {
        if (blank($text)) {
            return null;
        }

        $response = Http::asForm()
            ->withHeaders(['Authorization' => "DeepL-Auth-Key {$this->apiKey}"])
            ->timeout(15)
            ->post(rtrim($this->apiUrl, '/').'/v2/translate', [
                'text' => $text,
                'source_lang' => $this->toDeepLLanguageCode($from),
                'target_lang' => $this->toDeepLLanguageCode($to),
            ]);

        // Auth error, rate limit, quota exceeded, server error, or a transport
        // timeout all surface here; throw so the queue retries the whole job.
        $response->throw();

        $translated = data_get($response->json(), 'translations.0.text');

        return blank($translated) ? null : $translated;
    }

    public function detect(string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        // Ask DeepL to auto-detect by OMITTING source_lang; every translate
        // response carries `detected_source_language`. We only need the detected
        // language, so send a short snippet to a fixed target to keep the billed
        // character count minimal — the translated text itself is discarded.
        $response = Http::asForm()
            ->withHeaders(['Authorization' => "DeepL-Auth-Key {$this->apiKey}"])
            ->timeout(15)
            ->post(rtrim($this->apiUrl, '/').'/v2/translate', [
                'text' => mb_substr($text, 0, 300),
                'target_lang' => 'EN-GB',
            ]);

        // A transport/auth/rate-limit error throws so the queued job retries.
        $response->throw();

        $detected = data_get($response->json(), 'translations.0.detected_source_language');

        // DeepL returns an uppercase base code (EN, NL, DE); normalise to a
        // lowercase app-style locale, or null when it couldn't tell.
        return blank($detected) ? null : strtolower(explode('-', (string) $detected)[0]);
    }

    /**
     * DeepL expects uppercase language codes (EN, NL). Drop any region suffix
     * (en_US / en-GB -> EN) so app locales map cleanly.
     */
    private function toDeepLLanguageCode(string $locale): string
    {
        return strtoupper(explode('_', str_replace('-', '_', $locale))[0]);
    }
}
