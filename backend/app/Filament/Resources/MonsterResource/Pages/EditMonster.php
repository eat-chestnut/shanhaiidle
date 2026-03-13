<?php

namespace App\Filament\Resources\MonsterResource\Pages;

use App\Filament\Resources\MonsterResource;
use Filament\Resources\Pages\EditRecord;

class EditMonster extends EditRecord
{
    protected static string $resource = MonsterResource::class;

    private array $skillBindingsToSync = [];

    private array $dropBindingsToSync = [];

    private array $bossProfileToSync = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['skill_bindings'] = MonsterResource::skillBindingsForForm($this->record);
        $data['drop_bindings'] = MonsterResource::dropBindingsForForm($this->record);
        $data['boss_profile'] = MonsterResource::bossProfileForForm($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->skillBindingsToSync = MonsterResource::normalizeSkillBindingsOrFail($data['skill_bindings'] ?? []);
        $this->dropBindingsToSync = MonsterResource::normalizeDropBindingsOrFail($data['drop_bindings'] ?? []);
        $this->bossProfileToSync = MonsterResource::normalizeBossProfile($data['boss_profile'] ?? []);

        unset($data['skill_bindings'], $data['drop_bindings'], $data['boss_profile']);

        return $data;
    }

    protected function afterSave(): void
    {
        MonsterResource::syncRelations($this->record, $this->skillBindingsToSync, $this->dropBindingsToSync, $this->bossProfileToSync);
    }
}
