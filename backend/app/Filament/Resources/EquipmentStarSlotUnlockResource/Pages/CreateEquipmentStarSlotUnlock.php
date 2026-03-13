<?php

namespace App\Filament\Resources\EquipmentStarSlotUnlockResource\Pages;

use App\Filament\Resources\EquipmentStarSlotUnlockResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentStarSlotUnlock extends CreateRecord
{
    protected static string $resource = EquipmentStarSlotUnlockResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeSlotUnlockRowOrFail($data);
    }
}
