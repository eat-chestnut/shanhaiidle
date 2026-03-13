<?php

namespace App\Filament\Resources\EquipmentStarRuleResource\Pages;

use App\Filament\Resources\EquipmentStarRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentStarRules extends ListRecords
{
    protected static string $resource = EquipmentStarRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
