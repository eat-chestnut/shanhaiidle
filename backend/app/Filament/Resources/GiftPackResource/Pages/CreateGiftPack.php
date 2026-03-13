<?php

namespace App\Filament\Resources\GiftPackResource\Pages;

use App\Filament\Resources\GiftPackResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftPack extends CreateRecord
{
    protected static string $resource = GiftPackResource::class;

    private array $fixedItemsToSync = [];

    private array $selectableItemsToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $normalized = GiftPackResource::normalizeFormDataOrFail($data);
        $this->fixedItemsToSync = $normalized['fixed_items'];
        $this->selectableItemsToSync = $normalized['selectable_items'];

        return $normalized['pack'];
    }

    protected function afterCreate(): void
    {
        GiftPackResource::syncRelations($this->record, $this->fixedItemsToSync, $this->selectableItemsToSync);
    }
}
