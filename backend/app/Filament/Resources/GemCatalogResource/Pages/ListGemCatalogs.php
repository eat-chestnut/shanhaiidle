<?php

namespace App\Filament\Resources\GemCatalogResource\Pages;

use App\Filament\Resources\GemCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGemCatalogs extends ListRecords
{
    protected static string $resource = GemCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
