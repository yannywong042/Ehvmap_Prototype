<?php

namespace App\Filament\Resources\Experiences\Pages;

use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Resources\Experiences\Schemas\ExperienceForm;
use App\Models\Experience;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExperience extends CreateRecord
{
    protected static string $resource = ExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExperienceForm::autoTranslateAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ExperienceForm::pruneTranslationData($data);
    }

    /**
     * Admin-created stories are not auto-translated: the moderator manages
     * translations by hand (or with the "Automatisch vertalen" button), so we
     * suppress the on-create translation job here. The public /submit flow is
     * unaffected and still auto-translates.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return Experience::withoutTranslationDispatch(
            fn (): Model => parent::handleRecordCreation($data),
        );
    }
}
