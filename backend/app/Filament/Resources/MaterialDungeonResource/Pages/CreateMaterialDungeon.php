<?php

namespace App\Filament\Resources\MaterialDungeonResource\Pages;

use App\Filament\Resources\MaterialDungeonResource;
use App\Support\MaterialDungeonSupport;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialDungeon extends CreateRecord
{
    protected static string $resource = MaterialDungeonResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['unlock_stage_id'] = filled($data['unlock_stage_id'] ?? null) ? (string) $data['unlock_stage_id'] : null;
        $data['level_configs'] = MaterialDungeonSupport::normalizeLevelConfigsOrFail($data['level_configs'] ?? []);
        $data['display_rewards'] = MaterialDungeonSupport::normalizeDisplayRewardsOrFail($data['display_rewards'] ?? []);
        $data['layer_rules'] = MaterialDungeonSupport::normalizeLayerRulesOrFail($data['layer_rules'] ?? []);

        return $data;
    }
}
