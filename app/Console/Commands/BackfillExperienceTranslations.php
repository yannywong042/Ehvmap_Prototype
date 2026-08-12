<?php

namespace App\Console\Commands;

use App\Jobs\TranslateExperienceJob;
use App\Models\Experience;
use Illuminate\Console\Command;

class BackfillExperienceTranslations extends Command
{
    /**
     * @var string
     */
    protected $signature = 'experiences:backfill-translations
        {--from= : The locale existing stories were written in (defaults to the app fallback locale)}';

    /**
     * @var string
     */
    protected $description = 'Translate published stories that are missing translations for any supported locale.';

    public function handle(): int
    {
        $sourceLocale = (string) ($this->option('from') ?: config('app.fallback_locale', 'en'));

        /** @var list<string> $supportedLocales */
        $supportedLocales = config('translation.locales', []);

        $dispatched = 0;

        Experience::published()
            ->chunkById(100, function ($experiences) use ($supportedLocales, $sourceLocale, &$dispatched): void {
                foreach ($experiences as $experience) {
                    // Ensure legacy rows carry a source locale so the translated
                    // indicator and the job both know the original language.
                    if (blank($experience->source_locale)) {
                        $experience->forceFill(['source_locale' => $sourceLocale])->saveQuietly();
                    }

                    if ($this->isMissingAnyTranslation($experience, $supportedLocales)) {
                        TranslateExperienceJob::dispatch($experience, $experience->source_locale);
                        $dispatched++;
                    }
                }
            });

        $this->info("Dispatched translation for {$dispatched} experience(s).");

        return self::SUCCESS;
    }

    /**
     * A story needs backfilling when the description translation map has no
     * usable entry for any supported locale — never processed, a locale added
     * since, or a locale stored as an explicit null because no provider was
     * configured when it was first processed. Treating null/blank (not just an
     * absent key) as missing means running this command after configuring a real
     * provider fills in the placeholders left by the NullTranslator. Titles are
     * never translated, so they play no part in completeness.
     *
     * @param  list<string>  $supportedLocales
     */
    private function isMissingAnyTranslation(Experience $experience, array $supportedLocales): bool
    {
        $descriptions = $experience->description_translations ?? [];

        foreach ($supportedLocales as $locale) {
            if (blank($descriptions[$locale] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
