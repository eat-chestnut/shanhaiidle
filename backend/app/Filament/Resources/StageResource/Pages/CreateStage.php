<?php

namespace App\Filament\Resources\StageResource\Pages;

use App\Filament\Resources\StageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStage extends CreateRecord
{
    protected static string $resource = StageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['monsters_patch'] = StageResource::normalizeMonstersPatchInput($data['monsters_patch'] ?? []);
        StageResource::validateMonstersPatchOrFail($data['monsters_patch']);

        return $data;
    }
}
