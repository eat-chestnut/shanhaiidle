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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->normalMonstersToSync = MainStageDifficultyResource::normalizeMonsterEntriesOrFail($data['normal_monsters'] ?? [], 'normal');
        $this->eliteMonstersToSync = MainStageDifficultyResource::normalizeMonsterEntriesOrFail($data['elite_monsters'] ?? [], 'elite');
        $this->bossMonstersToSync = MainStageDifficultyResource::normalizeMonsterEntriesOrFail($data['boss_monsters'] ?? [], 'boss');

        unset($data['normal_monsters'], $data['elite_monsters'], $data['boss_monsters']);

        return $data;
    }

    protected function afterCreate(): void
    {
        MainStageDifficultyResource::syncMonsterEntries($this->record, $this->normalMonstersToSync, $this->eliteMonstersToSync, $this->bossMonstersToSync);
    }
}
