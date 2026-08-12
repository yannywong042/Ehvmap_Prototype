<?php

namespace App\Contracts;

interface Translator
{
    /**
     * Translate a piece of text from one locale into another.
     *
     * Implementations must return the translated string, or null when the
     * translation could not be produced (provider failure, empty/broken
     * result, unsupported language pair, ...). Returning null — rather than
     * throwing — lets the caller keep the original text and retry later, so a
     * flaky provider never blocks story publication. Implementations MAY throw
     * for hard/transport failures; callers treat a thrown error the same as a
     * null result for a given locale.
     */
    public function translate(string $text, string $from, string $to): ?string;

    /**
     * Detect the language the given text is actually written in.
     *
     * Returns a lowercase base language code (e.g. "en", "nl", "de"), or null
     * when the provider cannot determine it. This is what lets the translation
     * pipeline translate *from the real source language* instead of trusting a
     * possibly-wrong stored label. A thrown error signals a transient provider
     * failure (retry); a null result means "detection unavailable", so the
     * caller falls back to the stored source locale.
     */
    public function detect(string $text): ?string;
}
