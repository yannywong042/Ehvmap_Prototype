<?php

namespace App\Filament\Resources\Experiences\Pages;

use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Resources\Experiences\Schemas\ExperienceForm;
use App\Models\Experience;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EditExperience extends EditRecord
{
    protected static string $resource = ExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExperienceForm::autoTranslateAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ExperienceForm::pruneTranslationData($data);
    }

    /**
     * Delete the previously stored photo from the public disk whenever the admin
     * replaces it with a different file or clears the field, so we never leave an
     * orphaned file behind or keep a record pointing at a stale path.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $previousPhotoPath = $record->getOriginal('photo_path');
        $newPhotoPath = $data['photo_path'] ?? null;

        if (filled($previousPhotoPath) && $previousPhotoPath !== $newPhotoPath) {
            Storage::disk('public')->delete($previousPhotoPath);
        }

        // Admin edits never auto-translate: the moderator's manually reviewed
        // translations (edited by hand or via "Automatisch vertalen") must not be
        // overwritten by a background job. /submit still auto-translates.
        return Experience::withoutTranslationDispatch(
            fn (): Model => parent::handleRecordUpdate($record, $data),
        );
    }
}
