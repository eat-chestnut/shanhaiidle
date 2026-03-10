<?php

namespace App\Filament\Resources\MaterialDungeonResource\Pages;

use App\Filament\Resources\MaterialDungeonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialDungeon extends EditRecord
{
    protected static string $resource = MaterialDungeonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
