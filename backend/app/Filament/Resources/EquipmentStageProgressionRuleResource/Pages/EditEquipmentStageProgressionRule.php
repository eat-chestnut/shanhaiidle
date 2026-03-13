<?php

namespace App\Filament\Resources\EquipmentStageProgressionRuleResource\Pages;

use App\Filament\Resources\EquipmentStageProgressionRuleResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentStageProgressionRule extends EditRecord
{
    protected static string $resource = EquipmentStageProgressionRuleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeProgressionRuleRowOrFail($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
