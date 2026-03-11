<?php

namespace App\Filament\Resources\EquipmentSetSourceResource\Pages;

use App\Filament\Resources\EquipmentSetSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentSetSource extends CreateRecord
{
    protected static string $resource = EquipmentSetSourceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EquipmentSetSourceResource::normalizeFormData($data);
    }
}
