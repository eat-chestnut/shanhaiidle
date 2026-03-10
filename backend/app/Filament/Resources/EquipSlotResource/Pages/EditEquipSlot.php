<?php

namespace App\Filament\Resources\EquipSlotResource\Pages;

use App\Filament\Resources\EquipSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipSlot extends EditRecord
{
    protected static string $resource = EquipSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
