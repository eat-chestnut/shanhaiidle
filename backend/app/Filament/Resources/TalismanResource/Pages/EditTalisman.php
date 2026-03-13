<?php

namespace App\Filament\Resources\TalismanResource\Pages;

use App\Filament\Resources\TalismanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTalisman extends EditRecord
{
    protected static string $resource = TalismanResource::class;

    private array $tiersToSync = [];

    private array $upgradeCostsToSync = [];

    private array $starLinksToSync = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['tiers'] = TalismanResource::tiersForForm($this->record);
        $data['upgrade_costs'] = TalismanResource::upgradeCostsForForm($this->record);
        $data['star_links'] = TalismanResource::starLinksForForm($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $normalized = TalismanResource::normalizeFormDataOrFail($data);
        $this->tiersToSync = $normalized['tiers'];
        $this->upgradeCostsToSync = $normalized['upgrade_costs'];
        $this->starLinksToSync = $normalized['star_links'];

        return $normalized['talisman'];
    }

    protected function afterSave(): void
    {
        TalismanResource::syncRelations($this->record, $this->tiersToSync, $this->upgradeCostsToSync, $this->starLinksToSync);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
