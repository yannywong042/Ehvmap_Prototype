<?php

namespace App\Filament\Resources\Experiences\Tables;

use App\Jobs\TranslateExperienceJob;
use App\Models\Category;
use App\Models\Experience;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ExperiencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->size(48),

                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('category.name')
                    ->label('Categorie')
                    ->badge()
                    ->color(fn (Experience $record): array => $record->category
                        ? Color::hex($record->category->color)
                        : Color::Gray)
                    ->placeholder('Geen categorie'),

                TextColumn::make('neighborhood')
                    ->label('Wijk')
                    ->searchable(),

                TextColumn::make('author_email')
                    ->label('E-mailadres')
                    ->default('Anoniem'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Ingediend')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'In behandeling',
                        'approved' => 'Goedgekeurd',
                        'rejected' => 'Afgewezen',
                    ]),

                SelectFilter::make('category_id')
                    ->label('Categorie')
                    ->options(fn (): array => Category::all()
                        ->mapWithKeys(fn (Category $category): array => [
                            $category->id => $category->nameForLocale('nl') ?? $category->slug,
                        ])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')
                    ->label('Goedkeuren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    // Only ask for confirmation when the experience can actually be published.
                    // Without a category from the list we block it immediately, so the admin
                    // gets the notification straight away instead of after confirming.
                    ->requiresConfirmation(fn (Experience $record): bool => $record->category_id !== null)
                    ->action(function (Experience $record): void {
                        if ($record->category_id === null) {
                            Notification::make()
                                ->danger()
                                ->title('Kies eerst een categorie')
                                ->body('Deze ervaring moet een categorie uit de lijst hebben voordat deze gepubliceerd kan worden.')
                                ->send();

                            return;
                        }

                        $record->update(['status' => 'approved']);
                    })
                    ->visible(fn (Experience $record) => $record->status !== 'approved'),

                Action::make('reject')
                    ->label('Afwijzen')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'rejected']))
                    ->visible(fn ($record) => $record->status !== 'rejected'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('translate')
                        ->label('Vertalen')
                        ->icon('heroicon-o-language')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Geselecteerde ervaringen vertalen')
                        ->modalDescription('Voor elke geselecteerde ervaring wordt een vertaalopdracht in de wachtrij gezet. Bestaande vertalingen worden bijgewerkt zodra de opdracht is verwerkt.')
                        ->action(function (Collection $records): void {
                            $fallbackLocale = (string) config('app.fallback_locale', 'en');

                            $records->each(function (Experience $record) use ($fallbackLocale): void {
                                // Legacy rows may predate the source_locale column;
                                // give them one so the job knows the original language.
                                if (blank($record->source_locale)) {
                                    $record->forceFill(['source_locale' => $fallbackLocale])->saveQuietly();
                                }

                                TranslateExperienceJob::dispatch($record, $record->source_locale);
                            });

                            $count = $records->count();

                            Notification::make()
                                ->success()
                                ->title('Vertaling gestart')
                                ->body("{$count} vertaalopdracht(en) in de wachtrij gezet.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
