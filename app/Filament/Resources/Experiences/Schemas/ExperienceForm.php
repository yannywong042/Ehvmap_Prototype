<?php

namespace App\Filament\Resources\Experiences\Schemas;

use App\Contracts\Translator;
use App\Filament\Forms\Components\LocationMap;
use App\Filament\Resources\Experiences\Pages\CreateExperience;
use App\Filament\Resources\Experiences\Pages\EditExperience;
use App\Models\Category;
use App\Models\Experience;
use App\Models\Neighbourhood;
use App\Services\Translation\NullTranslator;
use App\Services\WijkLookup;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExperienceForm
{
    /**
     * Dutch display names for the supported locales, used in the admin UI.
     *
     * @var array<string, string>
     */
    private const array LOCALE_NAMES = [
        'en' => 'Engels',
        'nl' => 'Nederlands',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ervaring')
                    ->schema([
                        FileUpload::make('photo_path')
                            ->label('Foto')
                            ->image()
                            ->disk('public')
                            ->directory('experiences')
                            ->visibility('public')
                            // Remove the previously stored file from the public disk
                            // when the admin replaces or clears the photo, so we don't
                            // leave orphaned files behind referenced by a stale path.
                            ->deleteUploadedFileUsing(function (?string $file): void {
                                if (filled($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            }),

                        TextInput::make('title')
                            ->label('Titel (originele tekst)')
                            ->helperText('De titel in de oorspronkelijke taal van de indiener. Titels worden niet vertaald: bezoekers zien in elke taal deze tekst.')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Beschrijving (originele tekst)')
                            ->helperText('De beschrijving in de oorspronkelijke taal van de indiener.')
                            ->required()
                            ->rows(4),

                        Select::make('source_locale')
                            ->label('Originele taal')
                            ->helperText('De taal waarin de titel en beschrijving hierboven zijn geschreven. Dit bepaalt vanuit welke taal wordt vertaald en welke talen je in de sectie "Vertalingen" kunt bewerken. Corrigeer dit als de gedetecteerde taal onjuist is.')
                            // Include the record's stored source even if it is a
                            // language the site doesn't (yet) offer as a UI locale,
                            // e.g. a submission auto-detected as German.
                            ->options(fn (?Experience $record): array => static::sourceLocaleOptions($record))
                            ->default(fn (?Experience $record): string => static::sourceLocale($record))
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->required()
                            ->live()
                            // When the source changes, the previously selected
                            // edit-target may no longer be valid — point it at the
                            // first locale that is now translatable.
                            ->afterStateUpdated(fn (Set $set, Get $get, ?Experience $record) => $set(
                                'translation_locale',
                                array_key_first(static::translatableLocaleOptions($get, $record)),
                            )),

                        Select::make('category_id')
                            ->label('Categorie')
                            ->options(fn (): array => Category::all()
                                ->mapWithKeys(fn (Category $category): array => [
                                    $category->id => $category->nameForLocale('nl') ?? $category->slug,
                                ])
                                ->all())
                            ->native(false)
                            ->required(fn (Get $get): bool => $get('status') === 'approved'),

                        Select::make('neighborhood')
                            ->label('Wijk')
                            ->options(function (?Experience $record, Get $get, WijkLookup $wijkLookup): array {
                                $options = Neighbourhood::orderBy('wijknaam')->pluck('wijknaam', 'wijknaam')->all();

                                // Keep the record's existing value selectable even if it
                                // doesn't match an official wijk (e.g. legacy free-text data),
                                // so the form doesn't fail validation just from opening it.
                                if (filled($record?->neighborhood) && ! isset($options[$record->neighborhood])) {
                                    $options[$record->neighborhood] = $record->neighborhood;
                                }

                                // Also keep the pin's currently resolved wijk selectable, since
                                // moving the pin auto-selects it even if the Neighbourhood table
                                // hasn't been (re)seeded from the same boundary dataset yet.
                                $wijkAtPin = static::wijkAtPin($get, $wijkLookup);

                                if (filled($wijkAtPin) && ! isset($options[$wijkAtPin])) {
                                    $options[$wijkAtPin] = $wijkAtPin;
                                }

                                return $options;
                            })
                            ->native(false)
                            ->live()
                            ->required(),

                        Text::make(fn (Get $get, WijkLookup $wijkLookup): string => sprintf(
                            'De geselecteerde wijk komt niet overeen met de wijk van de pin op de kaart (%s).',
                            static::wijkAtPin($get, $wijkLookup)
                        ))
                            ->color('warning')
                            ->visible(fn (Get $get, WijkLookup $wijkLookup): bool => static::wijkMismatches($get, $wijkLookup)),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'In behandeling',
                                'approved' => 'Goedgekeurd',
                                'rejected' => 'Afgewezen',
                            ])
                            ->live()
                            ->required(),
                    ]),

                Section::make('Vertalingen')
                    ->description('Beheer de vertaalde beschrijving per taal. Alleen de beschrijving wordt vertaald; de titel blijft in elke taal de originele tekst van de indiener. De originele tekst hierboven blijft ongewijzigd. Vertalingen worden niet automatisch bijgewerkt bij het opslaan; gebruik de knop "Automatisch vertalen" bovenaan de pagina.')
                    ->schema([
                        Select::make('translation_locale')
                            ->label('Taal om te bewerken')
                            ->options(fn (Get $get, ?Experience $record): array => static::translatableLocaleOptions($get, $record))
                            ->default(fn (Get $get, ?Experience $record): ?string => array_key_first(static::translatableLocaleOptions($get, $record)))
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live()
                            ->dehydrated(false),

                        ...static::translationFields(),
                    ])
                    ->visible(fn (Get $get, ?Experience $record): bool => static::translatableLocaleOptions($get, $record) !== []),

                Section::make('Locatie')
                    ->description('Sleep de pin op de kaart of zoek op adres om de locatie aan te passen.')
                    ->schema([
                        Hidden::make('latitude')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get, WijkLookup $wijkLookup) => static::syncWijkFromPin($set, $get, $wijkLookup))
                            ->rules(['nullable', 'numeric', 'between:-90,90']),
                        Hidden::make('longitude')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get, WijkLookup $wijkLookup) => static::syncWijkFromPin($set, $get, $wijkLookup))
                            ->rules(['nullable', 'numeric', 'between:-180,180']),

                        LocationMap::make('location')
                            ->label('Locatie op de kaart')
                            ->latitudeField('latitude')
                            ->longitudeField('longitude')
                            ->dehydrated(false),
                    ]),

                Section::make('Indiener')
                    ->description('Deze gegevens zijn ingevuld door de indiener en kunnen niet door de beheerder worden aangepast.')
                    ->schema([
                        TextInput::make('author_email')
                            ->label('E-mailadres')
                            ->disabled()
                            ->dehydrated(false),

                        Toggle::make('wants_events')
                            ->label('Wil evenementen ontvangen')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('category_suggestion')
                            ->label('Suggestie van indiener')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Experience $record): bool => filled($record?->category_suggestion)),
                    ]),
            ]);
    }

    private static function syncWijkFromPin(Set $set, Get $get, WijkLookup $wijkLookup): void
    {
        $wijkAtPin = static::wijkAtPin($get, $wijkLookup);

        if (filled($wijkAtPin)) {
            $set('neighborhood', $wijkAtPin);
        }
    }

    private static function wijkAtPin(Get $get, WijkLookup $wijkLookup): ?string
    {
        $latitude = $get('latitude');
        $longitude = $get('longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return $wijkLookup->find((float) $latitude, (float) $longitude);
    }

    private static function wijkMismatches(Get $get, WijkLookup $wijkLookup): bool
    {
        $selectedWijk = $get('neighborhood');

        if (blank($selectedWijk)) {
            return false;
        }

        $wijkAtPin = static::wijkAtPin($get, $wijkLookup);

        return filled($wijkAtPin) && $wijkAtPin !== $selectedWijk;
    }

    /**
     * The locale a story is authored in: the record's stored value, or the
     * active locale for a not-yet-persisted create form.
     */
    private static function sourceLocale(?Experience $record): string
    {
        return $record?->source_locale ?: app()->getLocale();
    }

    /**
     * The source locale as it currently stands in the FORM — the live
     * "source_locale" select if the admin has set/changed it, otherwise the
     * record's stored value. This is what makes the translation section react to
     * the admin correcting a mislabelled original language.
     */
    private static function sourceLocaleFrom(Get $get, ?Experience $record): string
    {
        $value = $get('source_locale');

        return is_string($value) && $value !== '' ? $value : static::sourceLocale($record);
    }

    /**
     * All supported locales as value => display-name, for the source-language and
     * edit-target dropdowns.
     *
     * @return array<string, string>
     */
    private static function localeNameOptions(): array
    {
        $options = [];

        foreach (config('translation.locales', []) as $locale) {
            $options[$locale] = self::LOCALE_NAMES[$locale] ?? strtoupper($locale);
        }

        return $options;
    }

    /**
     * Options for the source-language select: every supported locale, plus the
     * record's own stored source if language detection landed on something the
     * site doesn't list as a UI locale — so it stays visible and correctable.
     *
     * @return array<string, string>
     */
    private static function sourceLocaleOptions(?Experience $record): array
    {
        $options = static::localeNameOptions();

        $current = $record?->source_locale;

        if (filled($current) && ! isset($options[$current])) {
            $options[$current] = self::LOCALE_NAMES[$current] ?? strtoupper($current);
        }

        return $options;
    }

    /**
     * Supported locales the admin can edit a translation for: every supported
     * locale except the (live) source language, whose text is the original
     * fields above.
     *
     * @return array<string, string>
     */
    private static function translatableLocaleOptions(Get $get, ?Experience $record): array
    {
        $source = static::sourceLocaleFrom($get, $record);

        return array_filter(
            static::localeNameOptions(),
            fn (string $locale): bool => $locale !== $source,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * A description textarea per supported locale, each shown only when its
     * locale is the one selected in the "translation_locale" dropdown and it is
     * not the source locale. Fields keep their state (and persist) even while
     * hidden so switching locales never drops another locale's translation.
     * There is no title input: titles are never translated (issue #106).
     *
     * @return list<Textarea>
     */
    private static function translationFields(): array
    {
        $fields = [];

        foreach (config('translation.locales', []) as $locale) {
            $name = self::LOCALE_NAMES[$locale] ?? strtoupper($locale);

            $isEditable = fn (Get $get, ?Experience $record): bool => $get('translation_locale') === $locale
                && static::sourceLocaleFrom($get, $record) !== $locale;

            // Persist even while hidden so switching locales in the dropdown
            // never drops another locale's already-loaded translation; blank and
            // source-locale entries are pruned on save (see pruneTranslationData()).
            $fields[] = Textarea::make("description_translations.{$locale}")
                ->label("Beschrijving ({$name})")
                ->rows(4)
                ->visible($isEditable)
                ->dehydratedWhenHidden();
        }

        return $fields;
    }

    /**
     * Drop blank per-locale translation entries before persisting (e.g. the
     * always-hidden source-locale field), so the JSON column only ever holds
     * real translations. Shared by both pages' mutate hooks.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function pruneTranslationData(array $data): array
    {
        if (! array_key_exists('description_translations', $data)) {
            return $data;
        }

        $values = array_filter((array) $data['description_translations'], fn ($value): bool => filled($value));

        $data['description_translations'] = $values === [] ? null : $values;

        return $data;
    }

    /**
     * The "Automatisch vertalen" header action, shared by the Create and Edit
     * pages. It translates synchronously and writes the results into the form so
     * the admin reviews them before saving — it never dispatches the queued job.
     */
    public static function autoTranslateAction(): Action
    {
        return Action::make('autoTranslate')
            ->label('Automatisch vertalen')
            ->icon(Heroicon::OutlinedLanguage)
            ->action(fn (CreateExperience|EditExperience $livewire) => static::autoTranslate($livewire));
    }

    /**
     * Synchronously translate the current form's original description into every
     * other supported locale and populate the translation fields, so the admin
     * can review and edit the results before saving. The title is left alone —
     * it is shown as submitted in every locale. Provider failures for a locale
     * leave that locale untouched and raise a notification.
     */
    private static function autoTranslate(CreateExperience|EditExperience $livewire): void
    {
        $record = $livewire->getRecord();
        $description = (string) data_get($livewire->data, 'description', '');
        // Use the source language as set in the form, so auto-translate follows
        // the admin's correction rather than a stale stored label.
        $source = (string) (data_get($livewire->data, 'source_locale') ?: static::sourceLocale($record));

        $translator = app(Translator::class);

        if ($translator instanceof NullTranslator) {
            Notification::make()
                ->warning()
                ->title('Geen vertaalprovider geconfigureerd')
                ->body('De originele tekst is overgenomen. Stel DEEPL_API_KEY in voor echte vertalingen.')
                ->send();
        }

        $translatedAny = false;

        $targetLocales = array_filter(
            config('translation.locales', []),
            fn (string $locale): bool => $locale !== $source,
        );

        foreach ($targetLocales as $locale) {
            $translatedDescription = static::translateOrNull($translator, $description, $source, $locale);

            if ($translatedDescription === null) {
                Notification::make()
                    ->warning()
                    ->title("Vertaling mislukt voor {$locale}")
                    ->send();

                continue;
            }

            data_set($livewire->data, "description_translations.{$locale}", $translatedDescription);
            $translatedAny = true;
        }

        if ($translatedAny) {
            Notification::make()
                ->success()
                ->title('Vertalingen bijgewerkt')
                ->body('Controleer en pas de vertalingen zo nodig aan voordat je opslaat.')
                ->send();
        }
    }

    /**
     * Translate a single string, treating a thrown error, null, or blank result
     * as a failure (null) so the caller keeps the field unchanged.
     */
    private static function translateOrNull(Translator $translator, string $text, string $from, string $to): ?string
    {
        if (blank($text)) {
            return null;
        }

        try {
            $translated = $translator->translate($text, $from, $to);
        } catch (Throwable) {
            return null;
        }

        return blank($translated) ? null : $translated;
    }
}
