<?php

namespace App\Filament\Resources\DailyDungeonLevelResource\Pages;

use App\Filament\Resources\DailyDungeonLevelResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditDailyDungeonLevel extends EditRecord
{
    protected static string $resource = DailyDungeonLevelResource::class;

    private array $normalMonstersToSync = [];

    private array $eliteMonstersToSync = [];

    private array $bossMonstersToSync = [];

    private array $upgradeCostsToSync = [];

    private array $firstClearRewardsToSync = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToList')
                ->label('返回等级列表')
                ->url(fn (): string => DailyDungeonLevelResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['normal_monsters'] = DailyDungeonLevelResource::monsterEntriesForForm($this->record, 'normal');
        $data['elite_monsters'] = DailyDungeonLevelResource::monsterEntriesForForm($this->record, 'elite');
        $data['boss_monsters'] = DailyDungeonLevelResource::monsterEntriesForForm($this->record, 'boss');
        $data['upgrade_costs'] = DailyDungeonLevelResource::upgradeCostsForForm($this->record);
        $data['first_clear_rewards'] = DailyDungeonLevelResource::firstClearRewardsForForm($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $levelNo = (int) ($data['level_no'] ?? 0);

        $this->normalMonstersToSync = DailyDungeonLevelResource::normalizeMonsterEntriesOrFail($data['normal_monsters'] ?? [], 'normal');
        $this->eliteMonstersToSync = DailyDungeonLevelResource::normalizeMonsterEntriesOrFail($data['elite_monsters'] ?? [], 'elite');
        $this->bossMonstersToSync = DailyDungeonLevelResource::normalizeMonsterEntriesOrFail($data['boss_monsters'] ?? [], 'boss');
        $this->upgradeCostsToSync = DailyDungeonLevelResource::normalizeUpgradeCostsOrFail($data['upgrade_costs'] ?? [], $levelNo);
        $this->firstClearRewardsToSync = DailyDungeonLevelResource::normalizeFirstClearRewardsOrFail($data['first_clear_rewards'] ?? []);

        $data['is_max_level'] = $levelNo === 5;

        unset($data['normal_monsters'], $data['elite_monsters'], $data['boss_monsters'], $data['upgrade_costs'], $data['first_clear_rewards']);

        return $data;
    }

    protected function afterSave(): void
    {
        DailyDungeonLevelResource::syncRelations(
            $this->record,
            $this->normalMonstersToSync,
            $this->eliteMonstersToSync,
            $this->bossMonstersToSync,
            $this->upgradeCostsToSync,
            $this->firstClearRewardsToSync,
        );
    }
}
