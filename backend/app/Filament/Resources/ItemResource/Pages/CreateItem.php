<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use App\Services\ItemCatalogImportService;
use Filament\Resources\Pages\CreateRecord;

class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $mainType = trim((string) ($data['main_type'] ?? 'material'));
        $subType = trim((string) ($data['sub_type'] ?? ''));

        return [
            ...$data,
            'id' => (string) $data['item_id'],
            'item_name' => (string) $data['item_name'],
            'display_name' => (string) $data['display_name'],
            'quality' => ItemCatalogImportService::normalizeTier((string) ($data['quality'] ?? 'white')),
            'rarity' => ItemCatalogImportService::normalizeTier((string) ($data['rarity'] ?? 'white')),
            'name' => (string) $data['item_name'],
            'type' => ItemCatalogImportService::legacyType($mainType, $subType),
            'material_type' => ItemCatalogImportService::legacyMaterialType($mainType, $subType),
            'stack_limit' => max(1, (int) ($data['max_stack'] ?? 9999)),
        ];
    }
}
