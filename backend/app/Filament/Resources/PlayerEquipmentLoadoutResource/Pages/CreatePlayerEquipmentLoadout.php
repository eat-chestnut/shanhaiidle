<?php

namespace App\Filament\Resources\PlayerEquipmentLoadoutResource\Pages;

use App\Filament\Resources\PlayerEquipmentLoadoutResource;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreatePlayerEquipmentLoadout extends CreateRecord
{
    protected static string $resource = PlayerEquipmentLoadoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PlayerEquipmentInstanceModuleSupport::normalizeLoadoutRowOrFail($data);
    }
}
