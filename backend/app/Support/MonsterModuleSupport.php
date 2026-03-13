<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Monster;
use Illuminate\Validation\ValidationException;

class MonsterModuleSupport
{
    public static function monsterTypeOptions(): array
    {
        return [
            'normal' => '普通怪',
            'elite' => '精英怪',
            'boss' => 'Boss',
        ];
    }

    public static function aiTypeOptions(): array
    {
        return [
            'melee_chase' => '近战追击',
            'ranged_keep' => '远程拉扯',
            'elite_melee_skill' => '精英近战技能',
            'boss_pattern' => 'Boss 模式',
        ];
    }

    public static function rarityTagOptions(): array
    {
        return [
            'common' => '普通',
            'elite' => '精英',
            'boss' => 'Boss',
            'story_boss' => '剧情Boss',
        ];
    }

    public static function slotTypeOptions(): array
    {
        return [
            'basic_attack' => '普通攻击',
            'active' => '主动技能',
            'passive' => '被动效果',
            'ultimate' => '终结技能',
            'phase_skill' => '阶段技能',
        ];
    }

    public static function spawnTypeOptions(): array
    {
        return [
            'normal' => '普通怪',
            'elite' => '精英怪',
            'boss' => 'Boss',
        ];
    }

    public static function dropTypeOptions(): array
    {
        return [
            'fixed' => '固定掉落',
            'random' => '概率掉落',
            'guarantee' => '保底掉落',
        ];
    }

    public static function normalizeSkillBindings(mixed $raw): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $skillId = trim((string) ($row['skill_id'] ?? ''));
            if ($skillId === '') {
                continue;
            }

            $rows[] = [
                'skill_id' => $skillId,
                'slot_type' => trim((string) ($row['slot_type'] ?? 'basic_attack')),
                'trigger_priority' => max(0, (int) ($row['trigger_priority'] ?? 10)),
                'phase_limit' => filled($row['phase_limit'] ?? null) ? trim((string) $row['phase_limit']) : null,
                'sort_order' => max(0, (int) ($row['sort_order'] ?? ($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        return array_values($rows);
    }

    public static function validateSkillBindingsOrFail(array $rows): void
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $slotType = trim((string) ($row['slot_type'] ?? ''));
            if (trim((string) ($row['skill_id'] ?? '')) === '') {
                $errors["skill_bindings.{$index}.skill_id"] = '请填写技能 ID。';
            }
            if (! isset(self::slotTypeOptions()[$slotType])) {
                $errors["skill_bindings.{$index}.slot_type"] = '技能槽位类型非法。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function normalizeDropItems(mixed $raw): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $rows[] = [
                'item_id' => $itemId,
                'drop_type' => trim((string) ($row['drop_type'] ?? 'fixed')),
                'count_min' => max(1, (int) ($row['count_min'] ?? 1)),
                'count_max' => max(1, (int) ($row['count_max'] ?? 1)),
                'drop_rate' => max(0, min(1, (float) ($row['drop_rate'] ?? 1))),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? ($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        return array_values($rows);
    }

    public static function validateDropItemsOrFail(array $rows): void
    {
        $errors = [];
        $validItemIds = Item::query()->pluck('item_id')->all();
        $validItemLookup = array_fill_keys($validItemIds, true);

        foreach ($rows as $index => $row) {
            $itemId = trim((string) ($row['item_id'] ?? ''));
            $dropType = trim((string) ($row['drop_type'] ?? ''));
            $countMin = (int) ($row['count_min'] ?? 0);
            $countMax = (int) ($row['count_max'] ?? 0);
            $dropRate = (float) ($row['drop_rate'] ?? -1);

            if ($itemId === '') {
                $errors["drops.{$index}.item_id"] = '请选择掉落物品。';
            } elseif (! isset($validItemLookup[$itemId])) {
                $errors["drops.{$index}.item_id"] = '掉落物品不存在。';
            }

            if (! isset(self::dropTypeOptions()[$dropType])) {
                $errors["drops.{$index}.drop_type"] = '掉落类型非法。';
            }

            if ($countMin < 1) {
                $errors["drops.{$index}.count_min"] = '最小数量必须大于等于 1。';
            }

            if ($countMax < $countMin) {
                $errors["drops.{$index}.count_max"] = '最大数量不能小于最小数量。';
            }

            if ($dropRate < 0 || $dropRate > 1) {
                $errors["drops.{$index}.drop_rate"] = '掉落概率必须在 0 到 1 之间。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function normalizeBossProfile(mixed $raw): array
    {
        $row = is_array($raw) ? $raw : [];

        return [
            'phase_count' => max(1, (int) ($row['phase_count'] ?? 1)),
            'phase_rules' => array_values(array_filter(is_array($row['phase_rules'] ?? null) ? $row['phase_rules'] : [], fn ($item): bool => is_array($item))),
            'summon_rules' => array_values(array_filter(is_array($row['summon_rules'] ?? null) ? $row['summon_rules'] : [], fn ($item): bool => is_array($item))),
            'rage_rules' => array_values(array_filter(is_array($row['rage_rules'] ?? null) ? $row['rage_rules'] : [], fn ($item): bool => is_array($item))),
            'weak_point_rules' => array_values(array_filter(is_array($row['weak_point_rules'] ?? null) ? $row['weak_point_rules'] : [], fn ($item): bool => is_array($item))),
            'intro_text' => filled($row['intro_text'] ?? null) ? trim((string) $row['intro_text']) : null,
            'battle_bgm_id' => filled($row['battle_bgm_id'] ?? null) ? trim((string) $row['battle_bgm_id']) : null,
            'camera_rule' => filled($row['camera_rule'] ?? null) ? trim((string) $row['camera_rule']) : null,
            'entry_fx_key' => filled($row['entry_fx_key'] ?? null) ? trim((string) $row['entry_fx_key']) : null,
            'death_fx_key' => filled($row['death_fx_key'] ?? null) ? trim((string) $row['death_fx_key']) : null,
            'story_flag_on_clear' => filled($row['story_flag_on_clear'] ?? null) ? trim((string) $row['story_flag_on_clear']) : null,
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
        ];
    }

    public static function normalizeDifficultyMonsters(mixed $raw, string $spawnType): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $monsterId = trim((string) ($row['monster_id'] ?? ''));
            if ($monsterId === '') {
                continue;
            }

            $rows[] = [
                'monster_id' => $monsterId,
                'spawn_type' => $spawnType,
                'weight' => max(1, (int) ($row['weight'] ?? 100)),
                'min_count' => max(1, (int) ($row['min_count'] ?? 1)),
                'max_count' => max(1, (int) ($row['max_count'] ?? 1)),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? ($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        return array_values($rows);
    }

    public static function validateDifficultyMonstersOrFail(array $rows, string $spawnType): void
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $monsterId = trim((string) ($row['monster_id'] ?? ''));
            $monster = Monster::query()->where('monster_id', $monsterId)->first(['monster_id', 'monster_type']);

            if ($monsterId === '') {
                $errors["{$spawnType}_monsters.{$index}.monster_id"] = '请选择怪物。';
            } elseif (! $monster) {
                $errors["{$spawnType}_monsters.{$index}.monster_id"] = '怪物不存在。';
            } elseif ((string) $monster->monster_type !== $spawnType) {
                $errors["{$spawnType}_monsters.{$index}.monster_id"] = '怪物类型与当前区块不匹配。';
            }

            $minCount = (int) ($row['min_count'] ?? 0);
            $maxCount = (int) ($row['max_count'] ?? 0);
            if ($minCount < 1) {
                $errors["{$spawnType}_monsters.{$index}.min_count"] = '最小数量必须大于等于 1。';
            }
            if ($maxCount < $minCount) {
                $errors["{$spawnType}_monsters.{$index}.max_count"] = '最大数量不能小于最小数量。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
