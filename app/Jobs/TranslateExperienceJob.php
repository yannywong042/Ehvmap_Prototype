<?php

namespace App\Jobs;

use App\Contracts\Translator;
use App\Models\Experience;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Translates a story's description into every supported locale other than the
 * one it was written in, and stores the results additively.
 *
 * The title is deliberately NOT translated (issue #106): titles are short,
 * evocative fragments — place names, nicknames, dialect, wordplay — that a
 * machine translation flattens into something the author never wrote. It is
 * shown as submitted in every locale, so it is never sent to the provider, not
 * even for language detection.
 *
 * Design notes tied to issue #89:
 * - Additive: the original description column is never touched; only the
 *   description_translations JSON map is written, and existing keys are merged
 *   so nothing already translated is lost (AC3). The source locale's own entry
 *   is populated with the original text (no provider call).
 * - Non-blocking: dispatched on create/edit onto the queue, so a slow or failing
 *   provider never blocks submission or moderation (AC5).
 * - Two distinct outcomes per target locale:
 *   1. The provider throws (network error, timeout, rate limit) — a *transient*
 *      failure. The existing value is left untouched and the job throws at the
 *      end so the queue retries it later.
 *   2. The provider returns null/blank — no translation is *available* (e.g. no
 *      provider is configured, or the provider had nothing to return). This is a
 *      terminal, non-error state: the locale is stored as an explicit null so the
 *      reader falls back to the original and the "translated" flag stays hidden.
 *      The job does NOT retry for this — a translation is filled in later only by
 *      re-editing the story or running the backfill against a configured provider.
 */
class TranslateExperienceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  string  $sourceLocale  The locale the story was written in; every
     *                                other supported locale is translated from it.
     */
    public function __construct(
        public Experience $experience,
        public string $sourceLocale,
    ) {}

    public function handle(Translator $translator): void
    {
        // Re-fetch to pick up any edits since dispatch, and to no-op gracefully
        // if the story was deleted before the job ran.
        $experience = $this->experience->fresh();

        if ($experience === null) {
            return;
        }

        // Determine the language the text is ACTUALLY written in, rather than
        // trusting the stored source_locale (which is only the visitor's site
        // locale at submission time and is frequently wrong). A thrown detection
        // error is transient and bubbles so the queue retries; a null result
        // means detection is unavailable, so we fall back to the stored value.
        $fallbackSource = $experience->source_locale ?: (string) config('app.fallback_locale', 'en');
        $detected = $this->normalizeLocale($translator->detect(trim((string) $experience->description)));
        $source = $detected ?? $fallbackSource;

        $descriptionTranslations = $experience->description_translations ?? [];

        // If detection reveals the stored source was wrong, every existing
        // translation was produced from the wrong language — discard them so
        // they are rebuilt from the corrected source.
        if ($detected !== null && $detected !== $fallbackSource) {
            $descriptionTranslations = [];
        }

        $erroredLocales = [];

        foreach (config('translation.locales', []) as $locale) {
            // The source locale keeps the original text verbatim — never machine
            // translated, never overwritten.
            if ($locale === $source) {
                $descriptionTranslations[$locale] = $experience->description;

                continue;
            }

            // Only translate locales that don't already have a usable
            // translation — so an already-translated (or admin-corrected) locale
            // is left intact and we translate only what's missing.
            if (filled($descriptionTranslations[$locale] ?? null)) {
                continue;
            }

            try {
                $description = $this->normalize($translator->translate($experience->description, $source, $locale));
            } catch (Throwable) {
                // Transient provider failure — leave any existing translation
                // untouched and retry the whole job later (via the throw below).
                $erroredLocales[] = $locale;

                continue;
            }

            // A null here means no translation was available for this locale, and
            // is stored as such: the reader falls back to the original text and
            // hides the flag, and the backfill can still detect it as missing
            // once a provider is configured.
            $descriptionTranslations[$locale] = $description;
        }

        // saveQuietly() so persisting translations does not re-trigger the
        // model's updated event (which would re-dispatch this job). The detected
        // source is persisted so the "translated" flag points at the right
        // language even when the submitted label was wrong.
        $experience->forceFill([
            'source_locale' => $source,
            'description_translations' => $descriptionTranslations,
        ])->saveQuietly();

        if ($erroredLocales !== []) {
            throw new RuntimeException(sprintf(
                'Translation provider errored for experience %d; retrying locales: %s.',
                $experience->id,
                implode(', ', $erroredLocales),
            ));
        }
    }

    /**
     * Normalize a provider result to either a non-empty string or null, so a
     * blank response is treated the same as "no translation available".
     */
    private function normalize(?string $translated): ?string
    {
        return blank($translated) ? null : $translated;
    }

    /**
     * Normalize a detected language to a lowercase base code (EN-GB -> en,
     * nl_NL -> nl), or null when detection returned nothing usable.
     */
    private function normalizeLocale(?string $locale): ?string
    {
        return blank($locale)
            ? null
            : strtolower(explode('_', str_replace('-', '_', $locale))[0]);
    }
}
