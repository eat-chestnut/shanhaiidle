<?php

namespace App\Filament\Resources\MilestoneResource\Pages;

use App\Filament\Resources\MilestoneResource;
use Filament\Resources\Pages\EditRecord;

class EditMilestone extends EditRecord
{
    protected static string $resource = MilestoneResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['milestone_id'] ??= (string) $this->record->milestone_id;

        return MilestoneResource::normalizeFormDataOrFail($data, (string) $this->record->milestone_id);
    }
}
