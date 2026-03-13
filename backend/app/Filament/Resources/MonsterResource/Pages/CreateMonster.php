<?php

namespace App\Filament\Resources\MonsterResource\Pages;

use App\Filament\Resources\MonsterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMonster extends CreateRecord
{
    protected static string $resource = MonsterResource::class;

    private array $skillBindingsToSync = [];

    private array $dropBindingsToSync = [];

    private array $bossProfileToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->skillBindingsToSync = MonsterResource::normalizeSkillBindingsOrFail($data['skill_bindings'] ?? []);
        $this->dropBindingsToSync = MonsterResource::normalizeDropBindingsOrFail($data['drop_bindings'] ?? []);
        $this->bossProfileToSync = MonsterResource::normalizeBossProfile($data['boss_profile'] ?? []);

        unset($data['skill_bindings'], $data['drop_bindings'], $data['boss_profile']);

        return $data;
    }

    protected function afterCreate(): void
    {
        MonsterResource::syncRelations($this->record, $this->skillBindingsToSync, $this->dropBindingsToSync, $this->bossProfileToSync);
    }
}
