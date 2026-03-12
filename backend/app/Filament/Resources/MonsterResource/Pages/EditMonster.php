<?php

namespace App\Filament\Resources\MonsterResource\Pages;

use App\Filament\Resources\MonsterResource;
use Filament\Resources\Pages\EditRecord;

class EditMonster extends EditRecord
{
    protected static string $resource = MonsterResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['drops'] = MonsterResource::dropsForForm($data['drops'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['drops'] = MonsterResource::normalizeAndValidateDropsOrFail($data['drops'] ?? []);

        return $data;
    }
}
