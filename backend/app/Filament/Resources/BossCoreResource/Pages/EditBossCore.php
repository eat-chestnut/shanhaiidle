<?php

namespace App\Filament\Resources\BossCoreResource\Pages;

use App\Filament\Resources\BossCoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBossCore extends EditRecord
{
    protected static string $resource = BossCoreResource::class;

    private array $effectsToSync = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['effects'] = BossCoreResource::effectsForForm($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $normalized = BossCoreResource::normalizeFormDataOrFail($data);
        $this->effectsToSync = $normalized['effects'];

        return $normalized['core'];
    }

    protected function afterSave(): void
    {
        BossCoreResource::syncRelations($this->record, $this->effectsToSync);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
