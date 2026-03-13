<?php

namespace App\Filament\Resources\TalismanResource\Pages;

use App\Filament\Resources\TalismanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTalisman extends CreateRecord
{
    protected static string $resource = TalismanResource::class;

    private array $tiersToSync = [];

    private array $upgradeCostsToSync = [];

    private array $starLinksToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $normalized = TalismanResource::normalizeFormDataOrFail($data);
        $this->tiersToSync = $normalized['tiers'];
        $this->upgradeCostsToSync = $normalized['upgrade_costs'];
        $this->starLinksToSync = $normalized['star_links'];

        return $normalized['talisman'];
    }

    protected function afterCreate(): void
    {
        TalismanResource::syncRelations($this->record, $this->tiersToSync, $this->upgradeCostsToSync, $this->starLinksToSync);
    }
}
