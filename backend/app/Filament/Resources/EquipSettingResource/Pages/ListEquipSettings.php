<?php

namespace App\Filament\Resources\EquipSettingResource\Pages;

use App\Filament\Resources\EquipSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipSettings extends ListRecords
{
    protected static string $resource = EquipSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
