<?php

namespace App\Filament\Resources\EquipmentStarUpgradeCostResource\Pages;

use App\Filament\Resources\EquipmentStarUpgradeCostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentStarUpgradeCosts extends ListRecords
{
    protected static string $resource = EquipmentStarUpgradeCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
