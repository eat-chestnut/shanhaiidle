<?php

namespace App\Filament\Resources\PlayerEquipmentGemSlotResource\Pages;

use App\Filament\Resources\PlayerEquipmentGemSlotResource;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlayerEquipmentGemSlot extends EditRecord
{
    protected static string $resource = PlayerEquipmentGemSlotResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PlayerEquipmentInstanceModuleSupport::normalizeGemSlotRowOrFail($data, 'player_equipment_gem_slot', null, (int) $this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
