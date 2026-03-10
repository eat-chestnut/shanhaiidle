<?php

namespace App\Filament\Resources\BlueGearTemplateResource\Pages;

use App\Filament\Resources\BlueGearTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlueGearTemplates extends ListRecords
{
    protected static string $resource = BlueGearTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
