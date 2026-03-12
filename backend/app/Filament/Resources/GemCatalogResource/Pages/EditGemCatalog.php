<?php

namespace App\Filament\Resources\GemCatalogResource\Pages;

use App\Filament\Resources\GemCatalogResource;
use App\Support\GemEffectRegistry;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGemCatalog extends EditRecord
{
    protected static string $resource = GemCatalogResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return GemEffectRegistry::populateRecordFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'gem';

        return GemEffectRegistry::normalizeRecordDataOrFail($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
