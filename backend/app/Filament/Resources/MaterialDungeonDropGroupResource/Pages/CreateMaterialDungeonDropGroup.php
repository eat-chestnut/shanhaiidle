<?php

namespace App\Filament\Resources\MaterialDungeonDropGroupResource\Pages;

use App\Filament\Resources\MaterialDungeonDropGroupResource;
use App\Support\MaterialDungeonSupport;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialDungeonDropGroup extends CreateRecord
{
    protected static string $resource = MaterialDungeonDropGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['rewards'] = MaterialDungeonSupport::normalizeDropGroupRewardsOrFail($data['rewards'] ?? []);

        return $data;
    }
}
