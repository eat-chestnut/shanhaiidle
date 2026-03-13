<?php

namespace App\Filament\Resources\MainStageDifficultyResource\Pages;

use App\Filament\Resources\MainStageDifficultyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMainStageDifficulty extends CreateRecord
{
    protected static string $resource = MainStageDifficultyResource::class;

    private array $normalMonstersToSync = [];

    private array $eliteMonstersToSync = [];

    private array $bossMonstersToSync = [];

    private array $firstClearRewardsToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->normalMonstersToSync = MainStageDifficultyResource::normalizeMonsterEntriesOrFail($data['normal_monsters'] ?? [], 'normal');
        $this->eliteMonstersToSync = MainStageDifficultyResource::normalizeMonsterEntriesOrFail($data['elite_monsters'] ?? [], 'elite');
        $this->bossMonstersToSync = MainStageDifficultyResource::normalizeMonsterEntriesOrFail($data['boss_monsters'] ?? [], 'boss');
        $this->firstClearRewardsToSync = MainStageDifficultyResource::normalizeFirstClearRewardsOrFail($data['first_clear_rewards'] ?? []);

        unset($data['normal_monsters'], $data['elite_monsters'], $data['boss_monsters'], $data['first_clear_rewards']);

        return $data;
    }

    protected function afterCreate(): void
    {
        MainStageDifficultyResource::syncRelations($this->record, $this->normalMonstersToSync, $this->eliteMonstersToSync, $this->bossMonstersToSync, $this->firstClearRewardsToSync);
    }
}
