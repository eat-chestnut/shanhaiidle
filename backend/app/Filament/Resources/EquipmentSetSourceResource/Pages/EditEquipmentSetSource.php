<?php

namespace App\Filament\Resources\EquipmentSetSourceResource\Pages;

use App\Filament\Resources\EquipmentSetSourceResource;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentSetSource extends EditRecord
{
    protected static string $resource = EquipmentSetSourceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EquipmentSetSourceResource::normalizeFormData($data);
    }
}
