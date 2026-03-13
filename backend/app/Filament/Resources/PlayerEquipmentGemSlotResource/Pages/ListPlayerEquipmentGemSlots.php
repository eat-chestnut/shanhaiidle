<?php

namespace App\Filament\Resources\PlayerEquipmentGemSlotResource\Pages;

use App\Filament\Resources\PlayerEquipmentGemSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlayerEquipmentGemSlots extends ListRecords
{
    protected static string $resource = PlayerEquipmentGemSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
