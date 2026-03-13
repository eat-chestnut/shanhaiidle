<?php

namespace App\Filament\Resources\MonsterResource\Pages;

use App\Filament\Resources\MonsterResource;
use Filament\Resources\Pages\EditRecord;

class EditMonster extends EditRecord
{
    protected static string $resource = MonsterResource::class;

    private array $skillBindingsToSync = [];

    private array $dropItemsToSync = [];

    private array $bossProfileToSync = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['skill_bindings'] = MonsterResource::skillBindingsForForm($this->record);
        $data['drop_items'] = MonsterResource::dropItemsForForm($this->record);
        $data['boss_profile'] = MonsterResource::bossProfileForForm($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->skillBindingsToSync = MonsterResource::normalizeSkillBindingsOrFail($data['skill_bindings'] ?? []);
        $this->dropItemsToSync = MonsterResource::normalizeDropItemsOrFail($data['drop_items'] ?? []);
        $this->bossProfileToSync = MonsterResource::normalizeBossProfile($data['boss_profile'] ?? []);

        unset($data['skill_bindings'], $data['drop_items'], $data['boss_profile']);

        return $data;
    }

    protected function afterSave(): void
    {
        MonsterResource::syncRelations($this->record, $this->skillBindingsToSync, $this->dropItemsToSync, $this->bossProfileToSync);
    }
}
