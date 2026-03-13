<?php

namespace App\Filament\Resources\MilestoneResource\Pages;

use App\Filament\Resources\MilestoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMilestone extends CreateRecord
{
    protected static string $resource = MilestoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MilestoneResource::normalizeFormDataOrFail($data);
    }
}
