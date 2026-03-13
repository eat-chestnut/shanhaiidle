<?php

namespace App\Filament\Resources\PlayerEquipmentInstanceResource\Pages;

use App\Filament\Resources\PlayerEquipmentInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlayerEquipmentInstances extends ListRecords
{
    protected static string $resource = PlayerEquipmentInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
