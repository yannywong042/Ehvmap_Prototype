<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const array LOCALES = ['en', 'nl'];

    /**
     * The five canonical categories, fixed by the create_categories_table and
     * add_name_translations_to_categories_table migration defaults. This is
     * the single source of truth shared by CategorySeeder and the
     * CategoryFactory::canonical() state, so the two can never drift.
     *
     * @var array<string, array{color: string, en: string, nl: string}>
     */
    public const array CANONICAL = [
        'natuur' => ['color' => '#1B9C84', 'en' => 'Nature', 'nl' => 'Natuur'],
        'water' => ['color' => '#2D72D9', 'en' => 'Water', 'nl' => 'Water'],
        'cultuur' => ['color' => '#8A4FD0', 'en' => 'Culture', 'nl' => 'Cultuur'],
        'verhalen' => ['color' => '#E0457B', 'en' => 'Stories', 'nl' => 'Verhalen'],
        'eten' => ['color' => '#F2A93B', 'en' => 'Food & Drink', 'nl' => 'Eten & Drinken'],
    ];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name_translations',
        'color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
        ];
    }

    /**
     * @return HasMany<Experience, $this>
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    public function getNameAttribute(): string
    {
        return $this->nameForLocale(app()->getLocale()) ?? $this->slug;
    }

    public function nameForLocale(string $locale): ?string
    {
        return $this->name_translations[$locale] ?? null;
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name) ?: 'category';
        $original = $slug;
        $suffix = 2;

        while (
            self::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
