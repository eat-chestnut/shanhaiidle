<?php

namespace App\Filament\Resources\BlueAffixResource\Pages;

use App\Filament\Resources\BlueAffixResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlueAffix extends CreateRecord
{
    protected static string $resource = BlueAffixResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $pendingSlotRules = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $normalized = BlueAffixResource::normalizeFormDataOrFail($data);
        $this->pendingSlotRules = $normalized['slot_rules'];

        return $normalized['affix'];
    }

    protected function afterCreate(): void
    {
        BlueAffixResource::syncSlotRules($this->record, $this->pendingSlotRules);
        $this->record->load('slotRules');
    }
}
