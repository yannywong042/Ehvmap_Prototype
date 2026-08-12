<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Experience;
use App\Models\Neighbourhood;
use App\Services\WijkLookup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    /**
     * Category-themed title fragments and description sentences, keyed by
     * category slug, so a generated experience's title/description always
     * match the theme of its assigned category.
     *
     * @var array<string, array{subjects: list<string>, descriptors: list<string>, sentences: list<string>}>
     */
    private const array THEMES = [
        'natuur' => [
            'subjects' => [
                'Verborgen park', 'Oude kastanjeboom', 'Wilde bloemenweide', 'Stil bospad',
                'Vogelrijk plantsoen', 'Zonnig grasveld', 'Beschut bosje', 'Oud wandelpad',
            ],
            'descriptors' => [
                'achter de fabriek', 'naast het spoor', 'aan de rand van de wijk', 'bij het kanaal',
                'achter de school', 'in het plantsoen', 'langs de vijver', 'op de hoek van de straat',
            ],
            'sentences' => [
                'Tussen het groen hoor je vooral vogels en het ritselen van bladeren.',
                'Een plek waar de stad heel ver weg lijkt, ook al sta je er middenin.',
                'In het voorjaar kleurt dit plekje helemaal wit en geel van de bloesem.',
                'Een rustige plek om even bij te komen tussen de bomen.',
            ],
        ],
        'water' => [
            'subjects' => [
                'Stil meer', 'Kanaalzicht', 'Fontein op het plein', 'Oude sluis',
                'Watertoren', 'Vijver met eendjes', 'Bruggetje over de Dommel', 'Kade langs het water',
            ],
            'descriptors' => [
                'bij zonsondergang', 'in het centrum', 'achter de kerk', 'naast de brug',
                'op het industrieterrein', 'bij het park', 'in de oude stad', 'aan de rand van de wijk',
            ],
            'sentences' => [
                'Het water kabbelt hier rustig langs, zelfs op een drukke dag.',
                'Een mooie plek om even te zitten en naar de eendjes te kijken.',
                'Bij zonsondergang kleurt het water prachtig oranje.',
                'De geur van het kanaal hoort helemaal bij deze plek.',
            ],
        ],
        'cultuur' => [
            'subjects' => [
                'Muurschildering', 'Oud pakhuis vol kunst', 'Straatmuzikant', 'Galerie in een garage',
                'Beeld op het plein', 'Graffitimuur', 'Ateliergebouw', 'Cultureel podium',
            ],
            'descriptors' => [
                'bij de Berenkuil', 'in het Strijp', 'op de hoek', 'aan de Markt',
                'in een oude fabriekshal', 'achter het station', 'in de binnenstad', 'op het plein',
            ],
            'sentences' => [
                'De muren en gevels vertellen hier hun eigen creatieve verhaal.',
                'Een plek waar Eindhovense makers hun werk laten zien.',
                'Kleur en vorm springen hier meteen in het oog.',
                'Elke keer dat je hier komt zie je weer iets nieuws.',
            ],
        ],
        'verhalen' => [
            'subjects' => [
                'Mijn oma woonde hier', 'De plek van mijn eerste kus', 'Waar de kermis altijd stond',
                'Het schoolplein van toen', 'De bakkerij van vroeger', 'Het café van mijn vader',
                'Waar we vroeger voetbalden', 'De plek waar opa altijd zat',
            ],
            'descriptors' => [
                'net om de hoek', 'vlakbij de kerk', 'aan het einde van de straat', 'naast de oude school',
                'bij het buurthuis', 'op het pleintje', 'aan de rand van de wijk', 'bij het kanaal',
            ],
            'sentences' => [
                'Dit plekje draagt een herinnering die de buurt nooit vergeet.',
                'Vroeger zag het er hier heel anders uit, maar het gevoel bleef hetzelfde.',
                'Een verhaal dat van generatie op generatie wordt doorverteld.',
                'Iedereen in de buurt kent wel iemand met een herinnering aan deze plek.',
            ],
        ],
        'eten' => [
            'subjects' => [
                'Beste stroopwafels van de stad', 'Verborgen terras', 'Turkse bakker', 'Familierestaurant',
                'Foodtruck met verse frites', 'Buurtcafé met huisgemaakte taart', 'Marktkraam vol lekkers',
                'Bakkerij met verse broodjes',
            ],
            'descriptors' => [
                'in het Strijp', 'op de hoek van de straat', 'bij de markt', 'aan het plein',
                'in de oude stad', 'naast het station', 'op het terras', 'in de wijk',
            ],
            'sentences' => [
                'De geur die hier hangt trekt je meteen naar binnen.',
                'Al jaren een vaste stop voor iedereen die van lekker eten houdt.',
                'Vers, lokaal en altijd de moeite waard.',
                'Een plek waar je nooit met honger weer weggaat.',
            ],
        ],
    ];

    private const float DEFAULT_LATITUDE = 51.4416;

    private const float DEFAULT_LONGITUDE = 5.4697;

    /**
     * Resolved once per factory instance (not per row) so generating a batch
     * never issues a lookup query per experience.
     *
     * @var Collection<int, Category>|null
     */
    private ?Collection $categories = null;

    /**
     * @var Collection<int, Neighbourhood>|null
     */
    private ?Collection $neighbourhoods = null;

    private ?WijkLookup $wijkLookup = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->resolveCategory();
        $neighbourhood = $this->resolveNeighbourhood();
        $theme = self::THEMES[$category->slug] ?? self::THEMES['verhalen'];

        $subject = fake()->randomElement($theme['subjects']);
        $descriptor = fake()->randomElement($theme['descriptors']);
        $title = "{$subject} {$descriptor}";

        [$latitude, $longitude] = $this->resolveCoordinates($neighbourhood);

        return [
            'title' => $title,
            'description' => $title.'. '.implode(' ', fake()->randomElements($theme['sentences'], 2)),
            // The themed copy above is authored in Dutch.
            'source_locale' => 'nl',
            'category_id' => $category->id,
            'neighborhood' => $neighbourhood->wijknaam,
            'neighbourhood_id' => $neighbourhood->id,
            'photo_path' => null,
            'author_email' => fake()->optional(0.6)->safeEmail(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => 'approved',
            'wants_events' => fake()->boolean(30),
        ];
    }

    /**
     * Persist generated stories without fanning out a translation job per row.
     * Real submissions (Experience::create in the submit form) still translate;
     * seeded/test data is translated on demand by the backfill command.
     *
     * @param  iterable<string, mixed>|null  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        return Experience::withoutTranslationDispatch(
            fn () => parent::create($attributes, $parent),
        );
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected']);
    }

    /**
     * Attach a short landscape JPEG so list/featured cards render with media.
     */
    public function withPhoto(): static
    {
        return $this->afterCreating(function (Experience $experience): void {
            if (! function_exists('imagecreatetruecolor')) {
                return;
            }

            $image = imagecreatetruecolor(960, 480);
            $fill = imagecolorallocate(
                $image,
                random_int(60, 210),
                random_int(50, 170),
                random_int(40, 150),
            );
            imagefilledrectangle($image, 0, 0, 960, 480, $fill);

            ob_start();
            imagejpeg($image, quality: 82);
            $bytes = (string) ob_get_clean();
            imagedestroy($image);

            $path = 'experiences/'.$experience->getKey().'-'.uniqid('', true).'.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $bytes);
            $experience->forceFill(['photo_path' => $path])->saveQuietly();
        });
    }

    private function resolveCategory(): Category
    {
        if ($this->categories === null || $this->categories->isEmpty()) {
            $this->categories = Category::all();
        }

        if ($this->categories->isEmpty()) {
            $this->categories = new Collection([Category::factory()->canonical()->create()]);
        }

        return $this->categories->random();
    }

    private function resolveNeighbourhood(): Neighbourhood
    {
        if ($this->neighbourhoods === null || $this->neighbourhoods->isEmpty()) {
            $this->neighbourhoods = Neighbourhood::all();
        }

        if ($this->neighbourhoods->isEmpty()) {
            $this->neighbourhoods = new Collection([Neighbourhood::factory()->create()]);
        }

        return $this->neighbourhoods->random();
    }

    /**
     * Jitters a coordinate around the neighbourhood's centroid, verifying via
     * WijkLookup that the point still resolves back to the same neighbourhood
     * so a pin never contradicts its own `neighbourhood_id`. Falls back to
     * the exact centroid (always inside its own polygon) if jittering can't
     * find a verified point after a few tries.
     *
     * @return array{0: float, 1: float}
     */
    private function resolveCoordinates(Neighbourhood $neighbourhood): array
    {
        $centerLatitude = $neighbourhood->center_latitude ?? self::DEFAULT_LATITUDE;
        $centerLongitude = $neighbourhood->center_longitude ?? self::DEFAULT_LONGITUDE;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $latitude = $centerLatitude + fake()->randomFloat(5, -0.003, 0.003);
            $longitude = $centerLongitude + fake()->randomFloat(5, -0.003, 0.003);

            if ($this->wijkLookup()->find($latitude, $longitude) === $neighbourhood->wijknaam) {
                return [$latitude, $longitude];
            }
        }

        return [$centerLatitude, $centerLongitude];
    }

    private function wijkLookup(): WijkLookup
    {
        return $this->wijkLookup ??= app(WijkLookup::class);
    }
}
