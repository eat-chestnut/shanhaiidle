<?php

namespace App\Filament\Resources\StageResource\Pages;

use App\Filament\Resources\StageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStage extends CreateRecord
{
    protected static string $resource = StageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['difficulties'] = StageResource::normalizeAndValidateDifficultiesOrFail($data['difficulties'] ?? []);

        return $data;
    }
}
