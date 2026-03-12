<?php

namespace App\Filament\Resources\MaterialDungeonResource\Pages;

use App\Filament\Resources\MaterialDungeonResource;
use App\Support\MaterialDungeonSupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialDungeon extends EditRecord
{
    protected static string $resource = MaterialDungeonResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['level_configs'] = MaterialDungeonSupport::levelConfigsForForm($data['level_configs'] ?? []);
        $data['display_rewards'] = MaterialDungeonSupport::displayRewardsForForm($data['display_rewards'] ?? []);
        $data['layer_rules'] = MaterialDungeonSupport::layerRulesForForm($data['layer_rules'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['unlock_stage_id'] = filled($data['unlock_stage_id'] ?? null) ? (string) $data['unlock_stage_id'] : null;
        $data['level_configs'] = MaterialDungeonSupport::normalizeLevelConfigsOrFail($data['level_configs'] ?? []);
        $data['display_rewards'] = MaterialDungeonSupport::normalizeDisplayRewardsOrFail($data['display_rewards'] ?? []);
        $data['layer_rules'] = MaterialDungeonSupport::normalizeLayerRulesOrFail($data['layer_rules'] ?? []);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
