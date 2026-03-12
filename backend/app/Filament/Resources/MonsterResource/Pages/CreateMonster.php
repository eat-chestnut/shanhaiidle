<?php

namespace App\Filament\Resources\MonsterResource\Pages;

use App\Filament\Resources\MonsterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMonster extends CreateRecord
{
    protected static string $resource = MonsterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['drops'] = MonsterResource::normalizeAndValidateDropsOrFail($data['drops'] ?? []);

        return $data;
    }
}
