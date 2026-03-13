<?php

namespace App\Filament\Resources\PlayerEquipmentLoadoutResource\Pages;

use App\Filament\Resources\PlayerEquipmentLoadoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlayerEquipmentLoadouts extends ListRecords
{
    protected static string $resource = PlayerEquipmentLoadoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
