<?php

namespace App\Filament\Resources\EquipmentStarRuleResource\Pages;

use App\Filament\Resources\EquipmentStarRuleResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentStarRule extends EditRecord
{
    protected static string $resource = EquipmentStarRuleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeStarRuleRowOrFail($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
