<?php

namespace App\Filament\Resources\GiftPackResource\Pages;

use App\Filament\Resources\GiftPackResource;
use Filament\Resources\Pages\EditRecord;

class EditGiftPack extends EditRecord
{
    protected static string $resource = GiftPackResource::class;

    private array $fixedItemsToSync = [];

    private array $selectableItemsToSync = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['fixed_items'] = GiftPackResource::fixedItemsForForm($this->record);
        $data['selectable_items'] = GiftPackResource::selectableItemsForForm($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $normalized = GiftPackResource::normalizeFormDataOrFail($data);
        $this->fixedItemsToSync = $normalized['fixed_items'];
        $this->selectableItemsToSync = $normalized['selectable_items'];

        return $normalized['pack'];
    }

    protected function afterSave(): void
    {
        GiftPackResource::syncRelations($this->record, $this->fixedItemsToSync, $this->selectableItemsToSync);
    }
}
