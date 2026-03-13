<?php

namespace App\Filament\Resources\MonsterResource\Pages;

use App\Filament\Resources\MonsterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMonster extends CreateRecord
{
    protected static string $resource = MonsterResource::class;

    private array $skillBindingsToSync = [];

    private array $dropItemsToSync = [];

    private array $bossProfileToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->skillBindingsToSync = MonsterResource::normalizeSkillBindingsOrFail($data['skill_bindings'] ?? []);
        $this->dropItemsToSync = MonsterResource::normalizeDropItemsOrFail($data['drop_items'] ?? []);
        $this->bossProfileToSync = MonsterResource::normalizeBossProfile($data['boss_profile'] ?? []);

        unset($data['skill_bindings'], $data['drop_items'], $data['boss_profile']);

        return $data;
    }

    protected function afterCreate(): void
    {
        MonsterResource::syncRelations($this->record, $this->skillBindingsToSync, $this->dropItemsToSync, $this->bossProfileToSync);
    }
}
