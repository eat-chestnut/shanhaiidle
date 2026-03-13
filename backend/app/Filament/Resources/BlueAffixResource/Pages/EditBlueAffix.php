<?php

namespace App\Filament\Resources\BlueAffixResource\Pages;

use App\Filament\Resources\BlueAffixResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlueAffix extends EditRecord
{
    protected static string $resource = BlueAffixResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $pendingSlotRules = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BlueAffixResource::formDataForEdit($this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $normalized = BlueAffixResource::normalizeFormDataOrFail($data, $this->record);
        $this->pendingSlotRules = $normalized['slot_rules'];

        return $normalized['affix'];
    }

    protected function afterSave(): void
    {
        BlueAffixResource::syncSlotRules($this->record, $this->pendingSlotRules);
        $this->record->load('slotRules');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
