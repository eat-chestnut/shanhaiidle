<?php

namespace App\Filament\Resources\PlayerEquipmentInstanceResource\Pages;

use App\Filament\Resources\PlayerEquipmentInstanceResource;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreatePlayerEquipmentInstance extends CreateRecord
{
    protected static string $resource = PlayerEquipmentInstanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PlayerEquipmentInstanceModuleSupport::normalizeInstanceRowOrFail($data);
    }
}
