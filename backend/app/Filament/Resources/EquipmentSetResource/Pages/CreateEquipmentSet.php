<?php

namespace App\Filament\Resources\EquipmentSetResource\Pages;

use App\Filament\Resources\EquipmentSetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentSet extends CreateRecord
{
    protected static string $resource = EquipmentSetResource::class;

    private array $itemsToSync = [];

    private array $effectsToSync = [];

    private array $recipesToSync = [];

    private array $costItemsToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $normalized = EquipmentSetResource::normalizeFormDataOrFail($data);
        $this->itemsToSync = $normalized['items'];
        $this->effectsToSync = $normalized['effects'];
        $this->recipesToSync = $normalized['recipes'];
        $this->costItemsToSync = $normalized['cost_items'];

        return $normalized['set'];
    }

    protected function afterCreate(): void
    {
        EquipmentSetResource::syncRelations(
            $this->record,
            $this->itemsToSync,
            $this->effectsToSync,
            $this->recipesToSync,
            $this->costItemsToSync,
        );
    }
}
