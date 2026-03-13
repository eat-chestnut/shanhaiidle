<?php

namespace App\Filament\Resources\PlayerEquipmentLoadoutResource\Pages;

use App\Filament\Resources\PlayerEquipmentLoadoutResource;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlayerEquipmentLoadout extends EditRecord
{
    protected static string $resource = PlayerEquipmentLoadoutResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PlayerEquipmentInstanceModuleSupport::normalizeLoadoutRowOrFail($data, 'player_equipment_loadout', null, (int) $this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
