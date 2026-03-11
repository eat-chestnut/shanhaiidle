<?php

namespace App\Filament\Resources\EquipTemplateResource\Pages;

use App\Filament\Resources\EquipTemplateResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditEquipTemplate extends EditRecord
{
    protected static string $resource = EquipTemplateResource::class;

    public static bool $formActionsAreSticky = true;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return EquipTemplateResource::normalizeFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EquipTemplateResource::normalizeFormData($data);
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::ScreenTwoExtraLarge;
    }
}
