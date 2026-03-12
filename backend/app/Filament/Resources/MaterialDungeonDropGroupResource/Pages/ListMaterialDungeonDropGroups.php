<?php

namespace App\Filament\Resources\MaterialDungeonDropGroupResource\Pages;

use App\Filament\Resources\MaterialDungeonDropGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialDungeonDropGroups extends ListRecords
{
    protected static string $resource = MaterialDungeonDropGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
