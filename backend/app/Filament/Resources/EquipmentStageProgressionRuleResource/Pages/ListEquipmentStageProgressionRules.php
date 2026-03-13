<?php

namespace App\Filament\Resources\EquipmentStageProgressionRuleResource\Pages;

use App\Filament\Resources\EquipmentStageProgressionRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentStageProgressionRules extends ListRecords
{
    protected static string $resource = EquipmentStageProgressionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
