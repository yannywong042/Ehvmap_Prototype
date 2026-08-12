<?php

namespace App\Models;

use App\Jobs\TranslateExperienceJob;
use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'description_translations',
        'source_locale',
        'category_id',
        'category_suggestion',
        'neighborhood',
        'neighbourhood_id',
        'photo_path',
        'author_email',
        'latitude',
        'longitude',
        'status',
        'wants_events',
        'terms_accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wants_events' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'terms_accepted_at' => 'datetime',
            'description_translations' => 'array',
        ];
    }

    /**
     * Whether creating/editing a story should dispatch a translation job. Bulk
     * factory/seeder generation flips this off (see ExperienceFactory) so demo
     * data is not translated one job per row; backfill handles it instead.
     */
    public static bool $dispatchTranslations = true;

    /**
     * Translate each story exactly once, when it is first created, and
     * re-translate only when the original title/description is edited so stale
     * translations never linger. Translation is dispatched to the queue so
     * creation/moderation is never blocked by a slow or failing provider; the
     * original text is always kept and the job is retried later on failure.
     */
    protected static function booted(): void
    {
        // Record the language the story was authored in (the visitor's active
        // locale at submission time) unless it was set explicitly. This drives
        // both the translation source and the "translated" UI indicator.
        static::creating(function (Experience $experience): void {
            $experience->source_locale ??= app()->getLocale();
        });

        static::created(function (Experience $experience): void {
            if (static::$dispatchTranslations) {
                TranslateExperienceJob::dispatch($experience, $experience->source_locale);
            }
        });

        static::updated(function (Experience $experience): void {
            if (
                static::$dispatchTranslations
                && ($experience->wasChanged('title') || $experience->wasChanged('description'))
            ) {
                // The edit invalidates every existing machine translation, so
                // clear them; the job then re-detects the source language and
                // rebuilds each locale from scratch. A title edit still counts:
                // the title itself is never translated, but rewriting it usually
                // signals the story was reworked, so the source language is
                // re-detected rather than trusted.
                $experience->forceFill([
                    'description_translations' => null,
                ])->saveQuietly();

                TranslateExperienceJob::dispatch($experience, $experience->source_locale);
            }
        });
    }

    /**
     * Run a callback without dispatching translation jobs for any stories
     * created or edited inside it. Used by the factory/seeders to avoid fanning
     * out one translation job per generated row.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutTranslationDispatch(callable $callback): mixed
    {
        $previous = static::$dispatchTranslations;
        static::$dispatchTranslations = false;

        try {
            return $callback();
        } finally {
            static::$dispatchTranslations = $previous;
        }
    }

    /**
     * The locale-resolved description for display. Falls back to the original
     * submitted text when the requested locale has no translation yet.
     */
    public function descriptionForLocale(string $locale): string
    {
        return $this->description_translations[$locale] ?? $this->description ?? '';
    }

    /**
     * Convenience accessor mirroring Category::$name, resolving the description
     * for the active application locale so views can render it directly. There
     * is no title equivalent: the title is never translated, so views read
     * $experience->title directly.
     */
    public function getLocalizedDescriptionAttribute(): string
    {
        return $this->descriptionForLocale(app()->getLocale());
    }

    /**
     * The original, source-language description exactly as submitted.
     */
    public function getOriginalDescriptionAttribute(): string
    {
        return $this->description ?? '';
    }

    /**
     * Whether the text shown for the given locale is a translation of another
     * language: the story was authored in a different locale AND a non-empty
     * description translation exists for the requested one. Drives the
     * "translated" pill, which now governs the description alone — the title is
     * always the author's original text.
     */
    public function isTranslatedForLocale(string $locale): bool
    {
        return $this->source_locale !== $locale
            && filled($this->description_translations[$locale] ?? null);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'published']);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        // Always resolve against the "public" disk that the upload code writes
        // to, rather than the configured default disk. This keeps the generated
        // URL correct even when FILESYSTEM_DISK points elsewhere.
        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Neighbourhood, $this>
     */
    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            // The title is never translated: it is the author's original text in
            // every locale, so there is no separate "original_title" to toggle to.
            'title' => $this->title,
            'description' => $this->localized_description,
            'original_description' => $this->original_description,
            'source_locale' => $this->source_locale,
            'is_translated' => $this->isTranslatedForLocale(app()->getLocale()),
            'photo_url' => $this->photo_url,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'color' => $this->category->color,
            ] : null,
            'neighborhood' => $this->neighborhood,
            'neighbourhood_id' => $this->neighbourhood_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
