<?php

namespace App\Filament\Resources\EquipTemplateResource\Pages;

use App\Filament\Resources\EquipTemplateResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditEquipTemplate extends EditRecord
{
    protected static string $resource = EquipTemplateResource::class;

    public static bool $formActionsAreSticky = true;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['id'] = (string) $this->getRecord()->getKey();

        return EquipTemplateResource::normalizeFormData($data);
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ScreenTwoExtraLarge;
    }
}
