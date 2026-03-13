<?php

namespace App\Filament\Resources\BossCoreResource\Pages;

use App\Filament\Resources\BossCoreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBossCore extends CreateRecord
{
    protected static string $resource = BossCoreResource::class;

    private array $effectsToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $normalized = BossCoreResource::normalizeFormDataOrFail($data);
        $this->effectsToSync = $normalized['effects'];

        return $normalized['core'];
    }

    protected function afterCreate(): void
    {
        BossCoreResource::syncRelations($this->record, $this->effectsToSync);
    }
}
