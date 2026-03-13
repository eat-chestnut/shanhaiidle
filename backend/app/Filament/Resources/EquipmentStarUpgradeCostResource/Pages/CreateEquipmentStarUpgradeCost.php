<?php

namespace App\Filament\Resources\EquipmentStarUpgradeCostResource\Pages;

use App\Filament\Resources\EquipmentStarUpgradeCostResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentStarUpgradeCost extends CreateRecord
{
    protected static string $resource = EquipmentStarUpgradeCostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeUpgradeCostRowOrFail($data);
    }
}
