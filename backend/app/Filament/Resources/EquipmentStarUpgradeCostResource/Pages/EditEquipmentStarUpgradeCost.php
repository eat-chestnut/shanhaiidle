<?php

namespace App\Filament\Resources\EquipmentStarUpgradeCostResource\Pages;

use App\Filament\Resources\EquipmentStarUpgradeCostResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentStarUpgradeCost extends EditRecord
{
    protected static string $resource = EquipmentStarUpgradeCostResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeUpgradeCostRowOrFail($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
