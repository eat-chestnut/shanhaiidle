<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use App\Support\ItemEffectRegistry;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ItemEffectRegistry::populateRecordFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ItemEffectRegistry::normalizeRecordDataOrFail($data);
    }
}
