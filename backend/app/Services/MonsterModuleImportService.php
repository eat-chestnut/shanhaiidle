<?php

namespace App\Services;

use App\Models\MainStageDifficulty;
use App\Models\Monster;
use App\Models\StageDifficultyMonster;
use App\Support\MonsterModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MonsterModuleImportService
{
    public const MONSTER_DATA_FILE = '../data/monsters.json';

    public const MAIN_STAGE_DATA_FILE = '../data/main_stage_module_v1.json';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function loadMonsterRows(): array
    {
        $path = base_path(self::MONSTER_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到怪物数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('怪物 JSON 解析失败。');
        }

        $rows = $decoded['monsters'] ?? null;
        if (! is_array($rows)) {
            throw new RuntimeException('monsters 节点缺失。');
        }

        self::validateMonsterRowsOrFail($rows);

        return array_values($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function loadDifficultyMonsterRows(): array
    {
        $path = base_path(self::MAIN_STAGE_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到主线模块数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('主线模块 JSON 解析失败。');
        }

        $chapters = $decoded['main_stage_module']['chapters'] ?? null;
        if (! is_array($chapters)) {
            throw new RuntimeException('main_stage_module.chapters 缺失。');
        }

        $rows = [];
        foreach ($chapters as $chapter) {
            if (! is_array($chapter) || ! is_array($chapter['difficulties'] ?? null)) {
                continue;
            }

            foreach ($chapter['difficulties'] as $difficulty) {
                if (! is_array($difficulty)) {
                    continue;
                }

                $difficultyId = trim((string) ($difficulty['difficulty_id'] ?? ''));
                if ($difficultyId === '') {
                    continue;
                }

                foreach (is_array($difficulty['monster_entries'] ?? null) ? $difficulty['monster_entries'] : [] as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $rows[] = [
                        'difficulty_id' => $difficultyId,
                        'monster_id' => trim((string) ($entry['monster_id'] ?? '')),
                        'spawn_type' => trim((string) ($entry['spawn_type'] ?? '')),
                        'weight' => max(1, (int) ($entry['weight'] ?? 100)),
                        'min_count' => max(1, (int) ($entry['min_count'] ?? 1)),
                        'max_count' => max(1, (int) ($entry['max_count'] ?? 1)),
                        'sort_order' => max(0, (int) ($entry['sort_order'] ?? 0)),
                        'is_enabled' => (bool) ($entry['is_enabled'] ?? true),
                        'remark' => filled($entry['remark'] ?? null) ? trim((string) $entry['remark']) : null,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return array{monster_count:int,difficulty_entry_count:int}
     */
    public static function importFromProjectFiles(): array
    {
        $monsterRows = self::loadMonsterRows();
        $difficultyRows = self::loadDifficultyMonsterRows();

        return DB::transaction(function () use ($monsterRows, $difficultyRows): array {
            $monsterIds = [];

            foreach ($monsterRows as $row) {
                $monsterId = trim((string) ($row['monster_id'] ?? ''));
                $monsterIds[] = $monsterId;

                $monster = Monster::query()->updateOrCreate(
                    ['monster_id' => $monsterId],
                    [
                        'monster_name' => (string) $row['monster_name'],
                        'display_name' => (string) $row['display_name'],
                        'monster_type' => (string) $row['monster_type'],
                        'chapter_id' => (string) $row['chapter_id'],
                        'display_stage_id' => filled($row['display_stage_id'] ?? null) ? (string) $row['display_stage_id'] : null,
                        'family' => filled($row['family'] ?? null) ? (string) $row['family'] : null,
                        'title' => filled($row['title'] ?? null) ? (string) $row['title'] : null,
                        'desc' => filled($row['desc'] ?? null) ? (string) $row['desc'] : null,
                        'icon' => filled($row['icon'] ?? null) ? (string) $row['icon'] : null,
                        'sprite' => filled($row['sprite'] ?? null) ? (string) $row['sprite'] : null,
                        'prefab_key' => filled($row['prefab_key'] ?? null) ? (string) $row['prefab_key'] : null,
                        'level' => (int) $row['level'],
                        'hp' => (int) $row['hp'],
                        'atk' => (int) $row['atk'],
                        'def' => (int) $row['def'],
                        'speed' => (int) $row['speed'],
                        'move_speed' => (int) $row['move_speed'],
                        'attack_range' => (int) $row['attack_range'],
                        'attack_interval' => (float) $row['attack_interval'],
                        'aggro_range' => (int) $row['aggro_range'],
                        'ai_type' => (string) $row['ai_type'],
                        'rarity_tag' => (string) $row['rarity_tag'],
                        'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                        'sort_order' => (int) ($row['sort_order'] ?? 0),
                        'remark' => filled($row['remark'] ?? null) ? (string) $row['remark'] : null,
                    ],
                );

                $monster->skillBindings()->delete();
                foreach (MonsterModuleSupport::normalizeSkillBindings($row['skill_bindings'] ?? []) as $binding) {
                    $monster->skillBindings()->create($binding);
                }

                $monster->dropBindings()->delete();
                foreach (MonsterModuleSupport::normalizeDropBindings($row['drop_bindings'] ?? []) as $binding) {
                    $monster->dropBindings()->create($binding);
                }

                if ((string) $monster->monster_type === 'boss') {
                    $monster->bossProfile()->updateOrCreate(
                        ['monster_id' => $monsterId],
                        MonsterModuleSupport::normalizeBossProfile($row['boss_profile'] ?? []),
                    );
                } else {
                    $monster->bossProfile()->delete();
                }
            }

            Monster::query()->whereNotIn('monster_id', $monsterIds)->delete();

            self::validateDifficultyMonsterRowsOrFail($difficultyRows);

            StageDifficultyMonster::query()->delete();
            foreach ($difficultyRows as $row) {
                StageDifficultyMonster::query()->create($row);
            }

            return [
                'monster_count' => count($monsterIds),
                'difficulty_entry_count' => count($difficultyRows),
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function validateMonsterRowsOrFail(array $rows): void
    {
        $errors = [];
        $seenMonsterIds = [];

        foreach ($rows as $index => $row) {
            $monsterId = trim((string) ($row['monster_id'] ?? ''));
            $monsterType = trim((string) ($row['monster_type'] ?? ''));

            if ($monsterId === '') {
                $errors["monsters.{$index}.monster_id"] = '请填写 monster_id。';
            } elseif (isset($seenMonsterIds[$monsterId])) {
                $errors["monsters.{$index}.monster_id"] = sprintf('monster_id 重复：%s。', $monsterId);
            } else {
                $seenMonsterIds[$monsterId] = true;
            }

            if (trim((string) ($row['monster_name'] ?? '')) === '') {
                $errors["monsters.{$index}.monster_name"] = '请填写怪物名称。';
            }
            if (trim((string) ($row['display_name'] ?? '')) === '') {
                $errors["monsters.{$index}.display_name"] = '请填写怪物展示名。';
            }
            if (! isset(MonsterModuleSupport::monsterTypeOptions()[$monsterType])) {
                $errors["monsters.{$index}.monster_type"] = '怪物类型非法。';
            }
            if (trim((string) ($row['chapter_id'] ?? '')) === '') {
                $errors["monsters.{$index}.chapter_id"] = '请填写所属章节。';
            }
            if (! isset(MonsterModuleSupport::aiTypeOptions()[(string) ($row['ai_type'] ?? '')])) {
                $errors["monsters.{$index}.ai_type"] = 'AI 类型非法。';
            }
            if (! isset(MonsterModuleSupport::rarityTagOptions()[(string) ($row['rarity_tag'] ?? '')])) {
                $errors["monsters.{$index}.rarity_tag"] = '稀有标签非法。';
            }

            foreach (['level', 'hp', 'atk', 'def', 'speed', 'move_speed', 'attack_range', 'aggro_range'] as $field) {
                if (! is_numeric($row[$field] ?? null) || (int) ($row[$field] ?? 0) < 0) {
                    $errors["monsters.{$index}.{$field}"] = sprintf('%s 必须是非负数。', $field);
                }
            }

            if (! is_numeric($row['attack_interval'] ?? null) || (float) ($row['attack_interval'] ?? 0) <= 0) {
                $errors["monsters.{$index}.attack_interval"] = 'attack_interval 必须大于 0。';
            }

            $skillBindings = MonsterModuleSupport::normalizeSkillBindings($row['skill_bindings'] ?? []);
            MonsterModuleSupport::validateSkillBindingsOrFail($skillBindings);

            $dropBindings = MonsterModuleSupport::normalizeDropBindings($row['drop_bindings'] ?? []);
            MonsterModuleSupport::validateDropBindingsOrFail($dropBindings);

            if ($monsterType === 'boss') {
                $bossProfile = MonsterModuleSupport::normalizeBossProfile($row['boss_profile'] ?? []);
                if ((int) ($bossProfile['phase_count'] ?? 0) < 1) {
                    $errors["monsters.{$index}.boss_profile.phase_count"] = 'Boss 阶段数至少为 1。';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function validateDifficultyMonsterRowsOrFail(array $rows): void
    {
        $errors = [];
        $groupedByDifficulty = [];

        foreach ($rows as $index => $row) {
            $difficultyId = trim((string) ($row['difficulty_id'] ?? ''));
            $monsterId = trim((string) ($row['monster_id'] ?? ''));
            $spawnType = trim((string) ($row['spawn_type'] ?? ''));

            if ($difficultyId === '' || ! MainStageDifficulty::query()->where('difficulty_id', $difficultyId)->exists()) {
                $errors["difficulty_monsters.{$index}.difficulty_id"] = '主线难度不存在，请先导入主线模块。';
            }

            if (! isset(MonsterModuleSupport::spawnTypeOptions()[$spawnType])) {
                $errors["difficulty_monsters.{$index}.spawn_type"] = 'spawn_type 非法。';
            }

            $monster = Monster::query()->where('monster_id', $monsterId)->first(['monster_id', 'monster_type', 'chapter_id']);
            if (! $monster) {
                $errors["difficulty_monsters.{$index}.monster_id"] = '怪物不存在。';
            } elseif ((string) $monster->monster_type !== $spawnType) {
                $errors["difficulty_monsters.{$index}.monster_id"] = '主线难度怪物类型与 spawn_type 不匹配。';
            } elseif ($difficultyId !== '') {
                $difficulty = MainStageDifficulty::query()
                    ->where('difficulty_id', $difficultyId)
                    ->first(['difficulty_id', 'chapter_id']);

                if ($difficulty && (string) ($monster->chapter_id ?? '') !== '' && (string) $monster->chapter_id !== (string) $difficulty->chapter_id) {
                    $errors["difficulty_monsters.{$index}.monster_id"] = '怪物所属章节与主线难度章节不一致。';
                }
            }

            if ((int) ($row['max_count'] ?? 0) < (int) ($row['min_count'] ?? 0)) {
                $errors["difficulty_monsters.{$index}.max_count"] = '最大数量不能小于最小数量。';
            }

            if ($difficultyId !== '' && isset(MonsterModuleSupport::spawnTypeOptions()[$spawnType])) {
                $groupedByDifficulty[$difficultyId][$spawnType] = true;
            }
        }

        $difficultyIds = MainStageDifficulty::query()->pluck('difficulty_id')->all();
        foreach ($difficultyIds as $difficultyId) {
            $spawnTypes = $groupedByDifficulty[$difficultyId] ?? [];
            foreach (['normal', 'elite', 'boss'] as $spawnType) {
                if (! isset($spawnTypes[$spawnType])) {
                    $errors["difficulty_monsters_required.{$difficultyId}.{$spawnType}"] = sprintf('%s 缺少 %s 怪物配置。', $difficultyId, $spawnType);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
