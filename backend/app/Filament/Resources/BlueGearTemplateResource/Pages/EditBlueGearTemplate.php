<?php

namespace App\Filament\Resources\BlueGearTemplateResource\Pages;

use App\Filament\Resources\BlueGearTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlueGearTemplate extends EditRecord
{
    protected static string $resource = BlueGearTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
