<?php

namespace App\Filament\Resources\MaterialDungeonDropGroupResource\Pages;

use App\Filament\Resources\MaterialDungeonDropGroupResource;
use App\Support\MaterialDungeonSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialDungeonDropGroup extends EditRecord
{
    protected static string $resource = MaterialDungeonDropGroupResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['rewards'] = MaterialDungeonSupport::dropGroupRewardsForForm($data['rewards'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rewards'] = MaterialDungeonSupport::normalizeDropGroupRewardsOrFail($data['rewards'] ?? []);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
