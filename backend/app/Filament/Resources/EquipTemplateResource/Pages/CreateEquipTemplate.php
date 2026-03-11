<?php

namespace App\Filament\Resources\EquipTemplateResource\Pages;

use App\Filament\Resources\EquipTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateEquipTemplate extends CreateRecord
{
    protected static string $resource = EquipTemplateResource::class;

    public static bool $formActionsAreSticky = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EquipTemplateResource::normalizeFormData($data);
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::ScreenTwoExtraLarge;
    }
}
