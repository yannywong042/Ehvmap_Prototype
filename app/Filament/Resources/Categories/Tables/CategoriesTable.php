<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ...collect(Category::LOCALES)
                    ->map(fn (string $locale) => TextColumn::make("name_{$locale}")
                        ->label(strtoupper($locale))
                        ->state(fn (Category $record): string => $record->nameForLocale($locale) ?? $record->slug))
                    ->all(),

                ColorColumn::make('color')
                    ->label('Kleur'),

                TextColumn::make('experiences_count')
                    ->label('Aantal ervaringen')
                    ->counts('experiences'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Category $record, DeleteAction $action): void {
                        if ($record->experiences()->count() > 0) {
                            Notification::make()
                                ->danger()
                                ->title('Categorie kan niet worden verwijderd')
                                ->body('Deze categorie heeft nog gekoppelde ervaringen. Wijs deze eerst een andere categorie toe.')
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action): void {
                            $blocked = $records->filter(fn (Category $category): bool => $category->experiences()->count() > 0);

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Categorieën kunnen niet worden verwijderd')
                                    ->body('Een of meer geselecteerde categorieën hebben nog gekoppelde ervaringen. Wijs deze eerst een andere categorie toe.')
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }
}
