<?php

namespace App\Filament\Resources\PlayerEquipmentGemSlotResource\Pages;

use App\Filament\Resources\PlayerEquipmentGemSlotResource;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreatePlayerEquipmentGemSlot extends CreateRecord
{
    protected static string $resource = PlayerEquipmentGemSlotResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PlayerEquipmentInstanceModuleSupport::normalizeGemSlotRowOrFail($data);
    }
}
