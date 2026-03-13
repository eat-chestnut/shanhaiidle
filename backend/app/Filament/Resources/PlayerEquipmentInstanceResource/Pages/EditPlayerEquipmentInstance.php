<?php

namespace App\Filament\Resources\PlayerEquipmentInstanceResource\Pages;

use App\Filament\Resources\PlayerEquipmentInstanceResource;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlayerEquipmentInstance extends EditRecord
{
    protected static string $resource = PlayerEquipmentInstanceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PlayerEquipmentInstanceModuleSupport::normalizeInstanceRowOrFail($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
