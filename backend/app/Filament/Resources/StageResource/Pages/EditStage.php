<?php

namespace App\Filament\Resources\StageResource\Pages;

use App\Filament\Resources\StageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStage extends EditRecord
{
    protected static string $resource = StageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['monsters_patch'] = StageResource::normalizeMonstersPatchInput($data['monsters_patch'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['monsters_patch'] = StageResource::normalizeMonstersPatchInput($data['monsters_patch'] ?? []);
        StageResource::validateMonstersPatchOrFail($data['monsters_patch']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
