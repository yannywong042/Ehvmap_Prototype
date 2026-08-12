<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...collect(Category::LOCALES)
                    ->map(fn (string $locale) => TextInput::make("name_translations.{$locale}")
                        ->label('Naam ('.strtoupper($locale).')')
                        ->required()
                        ->maxLength(255))
                    ->all(),

                ColorPicker::make('color')
                    ->label('Kleur')
                    ->required(),

                Hidden::make('slug'),
            ]);
    }
}
