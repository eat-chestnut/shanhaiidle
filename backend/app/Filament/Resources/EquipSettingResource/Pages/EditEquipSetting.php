<?php

namespace App\Filament\Resources\EquipSettingResource\Pages;

use App\Filament\Resources\EquipSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditEquipSetting extends EditRecord
{
    protected static string $resource = EquipSettingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EquipSettingResource::normalizeSocketWeights($data);
    }
}
