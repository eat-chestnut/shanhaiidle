<?php

namespace App\Filament\Resources\EquipSettingResource\Pages;

use App\Filament\Resources\EquipSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipSetting extends CreateRecord
{
    protected static string $resource = EquipSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EquipSettingResource::normalizeSocketWeights($data);
    }
}
