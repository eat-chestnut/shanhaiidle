<?php

namespace App\Filament\Resources\EquipmentSetResource\Pages;

use App\Filament\Resources\EquipmentSetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentSet extends CreateRecord
{
    protected static string $resource = EquipmentSetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['thresholds'] = EquipmentSetResource::normalizeThresholdsInput($data['thresholds'] ?? []);
        $maxPieces = (int) ($data['piece_count'] ?? $data['max_pieces'] ?? 0);
        $data['max_pieces'] = $maxPieces;
        EquipmentSetResource::validateThresholdsOrFail(
            $data['thresholds'],
            $maxPieces,
            isset($data['stage']) ? (int) $data['stage'] : null,
        );

        return $data;
    }
}
