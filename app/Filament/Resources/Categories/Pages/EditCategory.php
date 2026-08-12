<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = $this->record->slug;

        return $data;
    }
}
