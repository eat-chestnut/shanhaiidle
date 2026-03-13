<?php

namespace App\Filament\Resources\EquipmentStarSlotUnlockResource\Pages;

use App\Filament\Resources\EquipmentStarSlotUnlockResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentStarSlotUnlock extends EditRecord
{
    protected static string $resource = EquipmentStarSlotUnlockResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeSlotUnlockRowOrFail($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
