<?php

namespace App\Filament\Resources\GemCatalogResource\Pages;

use App\Filament\Resources\GemCatalogResource;
use App\Support\GemEffectRegistry;
use Filament\Resources\Pages\CreateRecord;

class CreateGemCatalog extends CreateRecord
{
    protected static string $resource = GemCatalogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'gem';

        return GemEffectRegistry::normalizeRecordDataOrFail($data);
    }
}
