<?php

namespace App\Filament\Resources\EquipmentSetResource\Pages;

use App\Filament\Resources\EquipmentSetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentSet extends EditRecord
{
    protected static string $resource = EquipmentSetResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['thresholds'] = EquipmentSetResource::normalizeThresholdsInput($data['thresholds'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['thresholds'] = EquipmentSetResource::normalizeThresholdsInput($data['thresholds'] ?? []);
        EquipmentSetResource::validateThresholdsOrFail(
            $data['thresholds'],
            (int) ($data['piece_count'] ?? 0),
            isset($data['stage']) ? (int) $data['stage'] : null,
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
