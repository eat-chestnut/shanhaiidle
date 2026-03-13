<?php

namespace App\Filament\Resources\EquipmentStarRuleResource\Pages;

use App\Filament\Resources\EquipmentStarRuleResource;
use App\Support\EquipmentStarModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentStarRule extends CreateRecord
{
    protected static string $resource = EquipmentStarRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EquipmentStarModuleSupport::normalizeStarRuleRowOrFail($data);
    }
}
