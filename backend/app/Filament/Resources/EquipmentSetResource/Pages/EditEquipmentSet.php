<?php

namespace App\Filament\Resources\EquipmentSetResource\Pages;

use App\Filament\Resources\EquipmentSetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentSet extends EditRecord
{
    protected static string $resource = EquipmentSetResource::class;

    private array $itemsToSync = [];

    private array $effectsToSync = [];

    private array $recipesToSync = [];

    private array $costItemsToSync = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['set_items'] = EquipmentSetResource::setItemsForForm($this->record);
        $data['effects'] = EquipmentSetResource::effectsForForm($this->record);
        $data['recipes'] = EquipmentSetResource::recipesForForm($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $normalized = EquipmentSetResource::normalizeFormDataOrFail($data);
        $this->itemsToSync = $normalized['items'];
        $this->effectsToSync = $normalized['effects'];
        $this->recipesToSync = $normalized['recipes'];
        $this->costItemsToSync = $normalized['cost_items'];

        return $normalized['set'];
    }

    protected function afterSave(): void
    {
        EquipmentSetResource::syncRelations(
            $this->record,
            $this->itemsToSync,
            $this->effectsToSync,
            $this->recipesToSync,
            $this->costItemsToSync,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
