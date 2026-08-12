<?php

namespace Tests\Support;

use App\Contracts\Translator;
use RuntimeException;

/**
 * Deterministic in-memory Translator used by the test suite instead of any real
 * provider. It exercises the whole pipeline without a network call.
 *
 * Modes:
 * - "prefix" (default): returns "[{to}] {text}" so translations are predictable.
 * - "throw": simulates a provider outage/timeout by throwing.
 * - "empty": simulates a broken/empty provider response.
 *
 * $detectAs simulates language detection: when set, detect() returns it; when
 * null, detection is "unavailable" (like the NullTranslator) and the caller
 * falls back to the stored source locale.
 */
class FakeTranslator implements Translator
{
    /**
     * Every text handed to translate(), in call order, so tests can assert what
     * was (and was not) sent to the provider.
     *
     * @var list<string>
     */
    public array $translated = [];

    /**
     * Every text handed to detect(), in call order.
     *
     * @var list<string>
     */
    public array $detected = [];

    public function __construct(
        private string $mode = 'prefix',
        private ?string $detectAs = null,
    ) {}

    public function translate(string $text, string $from, string $to): ?string
    {
        $this->translated[] = $text;

        return match ($this->mode) {
            'throw' => throw new RuntimeException('Translation provider unavailable.'),
            'empty' => '',
            default => "[{$to}] {$text}",
        };
    }

    public function detect(string $text): ?string
    {
        $this->detected[] = $text;

        // A provider outage affects detection too — surface it as a thrown error
        // so the pipeline treats it as transient.
        if ($this->mode === 'throw') {
            throw new RuntimeException('Translation provider unavailable.');
        }

        return $this->detectAs;
    }
}
