<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name_translations' => [
                'en' => Str::title($name),
                'nl' => Str::title($name),
            ],
            'color' => fake()->hexColor(),
        ];
    }

    /**
     * One of the five canonical categories (or a specific one by slug), with
     * slug, color, and translations matching Category::CANONICAL exactly —
     * the same source of truth CategorySeeder uses.
     */
    public function canonical(?string $slug = null): static
    {
        $slug ??= fake()->randomElement(array_keys(Category::CANONICAL));

        if (! isset(Category::CANONICAL[$slug])) {
            throw new InvalidArgumentException("[{$slug}] is not a canonical category slug.");
        }

        $attributes = Category::CANONICAL[$slug];

        return $this->state([
            'slug' => $slug,
            'color' => $attributes['color'],
            'name_translations' => [
                'en' => $attributes['en'],
                'nl' => $attributes['nl'],
            ],
        ]);
    }

    public function natuur(): static
    {
        return $this->canonical('natuur');
    }

    public function water(): static
    {
        return $this->canonical('water');
    }

    public function cultuur(): static
    {
        return $this->canonical('cultuur');
    }

    public function verhalen(): static
    {
        return $this->canonical('verhalen');
    }

    public function eten(): static
    {
        return $this->canonical('eten');
    }
}
