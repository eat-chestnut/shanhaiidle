<?php

namespace App\Filament\Resources\GemCatalogResource\Pages;

use App\Filament\Resources\GemCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGemCatalog extends EditRecord
{
    protected static string $resource = GemCatalogResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'gem';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
