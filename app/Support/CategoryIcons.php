<?php

namespace App\Support;

/**
 * Stroke SVG icons for the five canonical categories.
 * Paths use a 24×24 viewBox (Lucide-style) and inherit color via currentColor.
 */
final class CategoryIcons
{
    /**
     * @var array<string, list<string>>
     */
    public const array PATHS = [
        // Leaf
        'natuur' => [
            'M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z',
            'M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12',
        ],
        // Droplet
        'water' => [
            'M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z',
        ],
        // Landmark
        'cultuur' => [
            'M3 22h18',
            'M6 18v-7',
            'M10 18v-7',
            'M14 18v-7',
            'M18 18v-7',
            'M3 11h18',
            'm12 2 9 4.5v1H3v-1L12 2z',
        ],
        // Book open
        'verhalen' => [
            'M12 7v14',
            'M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z',
        ],
        // Utensils
        'eten' => [
            'M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2',
            'M7 2v20',
            'M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7',
        ],
    ];

    public static function resolveSlug(?string $slug): string
    {
        if ($slug !== null && array_key_exists($slug, self::PATHS)) {
            return $slug;
        }

        return 'natuur';
    }

    /**
     * @return list<string>
     */
    public static function pathsFor(?string $slug): array
    {
        return self::PATHS[self::resolveSlug($slug)];
    }
}
