<?php

namespace App\Filament\Resources\EquipmentStageProgressionRuleResource\Pages;

use App\Filament\Resources\EquipmentStageProgressionRuleResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentStageProgressionRule extends CreateRecord
{
    protected static string $resource = EquipmentStageProgressionRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeProgressionRuleRowOrFail($data);
    }
}
