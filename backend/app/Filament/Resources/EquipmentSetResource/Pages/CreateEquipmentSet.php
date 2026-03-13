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
        EquipmentSetResource::validateThresholdsOrFail(
            $data['thresholds'],
            (int) ($data['piece_count'] ?? 0),
            isset($data['stage']) ? (int) $data['stage'] : null,
        );

        return $data;
    }
}
