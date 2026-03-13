<?php

namespace App\Filament\Resources\BlueEquipmentTemplateResource\Pages;

use App\Filament\Resources\BlueEquipmentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlueEquipmentTemplate extends CreateRecord
{
    protected static string $resource = BlueEquipmentTemplateResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $pendingBaseStats = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $normalized = BlueEquipmentTemplateResource::normalizeFormDataOrFail($data);
        $this->pendingBaseStats = $normalized['base_stats'];

        return $normalized['template'];
    }

    protected function afterCreate(): void
    {
        BlueEquipmentTemplateResource::syncBaseStats($this->record, $this->pendingBaseStats);
        $this->record->load('baseStats');
    }
}
