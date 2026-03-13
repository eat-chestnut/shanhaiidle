<?php

namespace App\Filament\Resources\EquipmentStarSlotUnlockResource\Pages;

use App\Filament\Resources\EquipmentStarSlotUnlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentStarSlotUnlocks extends ListRecords
{
    protected static string $resource = EquipmentStarSlotUnlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
