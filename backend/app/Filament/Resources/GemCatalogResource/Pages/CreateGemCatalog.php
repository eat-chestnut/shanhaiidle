<?php

namespace App\Filament\Resources\GemCatalogResource\Pages;

use App\Filament\Resources\GemCatalogResource;
use App\Support\GemModuleSupport;
use Filament\Resources\Pages\CreateRecord;

class CreateGemCatalog extends CreateRecord
{
    protected static string $resource = GemCatalogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return GemModuleSupport::normalizeRowOrFail($data);
    }
}
