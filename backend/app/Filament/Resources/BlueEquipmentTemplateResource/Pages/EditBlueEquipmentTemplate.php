<?php

namespace App\Filament\Resources\BlueEquipmentTemplateResource\Pages;

use App\Filament\Resources\BlueEquipmentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlueEquipmentTemplate extends EditRecord
{
    protected static string $resource = BlueEquipmentTemplateResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $pendingBaseStats = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BlueEquipmentTemplateResource::formDataForEdit($this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $normalized = BlueEquipmentTemplateResource::normalizeFormDataOrFail($data, $this->record);
        $this->pendingBaseStats = $normalized['base_stats'];

        return $normalized['template'];
    }

    protected function afterSave(): void
    {
        BlueEquipmentTemplateResource::syncBaseStats($this->record, $this->pendingBaseStats);
        $this->record->load('baseStats');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
