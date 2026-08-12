<?php

namespace App\Services\Translation;

use App\Contracts\Translator;

/**
 * Safe default translation driver.
 *
 * This is a deliberate placeholder: choosing and integrating a concrete
 * machine-translation provider (DeepL, Google Translate, ...) is out of scope
 * for issue #89 and tracked as a separate technical spike. Until that provider
 * lands, the NullTranslator makes NO external calls and reports that NO
 * translation is available by returning null. Returning null (rather than a
 * copy of the original) means the story's translation columns are stored as
 * null, the reader falls back to the original text, and the "translated" flag
 * is hidden — nothing is presented as a translation when it isn't one.
 *
 * Swap this out by implementing App\Contracts\Translator against a real API and
 * pointing config('translation.driver') at the new class.
 */
class NullTranslator implements Translator
{
    public function translate(string $text, string $from, string $to): ?string
    {
        return null;
    }

    public function detect(string $text): ?string
    {
        // No provider configured, so language detection is unavailable; the
        // caller falls back to the story's stored source locale.
        return null;
    }
}
