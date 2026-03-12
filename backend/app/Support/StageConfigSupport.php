<?php

namespace App\Support;

use App\Models\Monster;
use Illuminate\Validation\ValidationException;

class StageConfigSupport
{
    /**
     * @var array<string, array{id:string,name:string,kind:string}>
     */
    private static array $monsterMetaCache = [];

    public static function defaultDifficulties(): array
    {
        return [static::defaultDifficultyRow()];
    }

    public static function defaultDifficultyRow(int $index = 0): array
    {
        return [
            'difficulty_name' => static::defaultDifficultyName($index),
            'recommended_power' => 0,
            'spawn_interval' => 1.6,
            'onscreen_limit' => 10,
            'spawn_radius' => 220,
            'normal_monsters' => [['monster_id' => 'mob_a', 'weight' => 100]],
            'elite_monsters' => [['monster_id' => 'elite_a', 'weight' => 100]],
            'boss_monsters' => [['monster_id' => 'boss_a', 'weight' => 100]],
            'elite_spawn_rule' => null,
            'boss_spawn_rule' => null,
        ];
    }

    public static function difficultiesForForm(mixed $stored): array
    {
        return static::normalizeDifficulties($stored);
    }

    public static function normalizeDifficulties(mixed $raw): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = static::normalizeDifficultyRow($row, (int) $index);
        }

        return $rows === [] ? static::defaultDifficulties() : array_values($rows);
    }

    public static function normalizeDifficultiesOrFail(mixed $raw): array
    {
        $rows = static::normalizeDifficulties($raw);
        static::validateDifficultiesOrFail($rows);

        return $rows;
    }

    public static function validateDifficultiesOrFail(array $rows): void
    {
        $errors = [];
        $seenNames = [];

        if ($rows === []) {
            $errors['difficulties'] = '请至少配置一个难度。';
        }

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["difficulties.{$index}"] = '难度配置格式错误。';
                continue;
            }

            $difficultyName = trim((string) ($row['difficulty_name'] ?? ''));
            if ($difficultyName === '') {
                $errors["difficulties.{$index}.difficulty_name"] = '请填写难度名。';
            } elseif (isset($seenNames[$difficultyName])) {
                $errors["difficulties.{$index}.difficulty_name"] = sprintf('难度名重复：%s。', $difficultyName);
            } else {
                $seenNames[$difficultyName] = true;
            }

            $recommendedPower = $row['recommended_power'] ?? null;
            if (! is_numeric($recommendedPower) || (int) $recommendedPower < 0) {
                $errors["difficulties.{$index}.recommended_power"] = '建议战力必须是不小于 0 的整数。';
            }

            $spawnInterval = $row['spawn_interval'] ?? null;
            if (! is_numeric($spawnInterval) || (float) $spawnInterval <= 0) {
                $errors["difficulties.{$index}.spawn_interval"] = '刷新间隔必须大于 0。';
            }

            $onscreenLimit = $row['onscreen_limit'] ?? null;
            if (! is_numeric($onscreenLimit) || (int) $onscreenLimit < 1) {
                $errors["difficulties.{$index}.onscreen_limit"] = '同屏上限必须大于等于 1。';
            }

            $spawnRadius = $row['spawn_radius'] ?? null;
            if (! is_numeric($spawnRadius) || (int) $spawnRadius <= 0) {
                $errors["difficulties.{$index}.spawn_radius"] = '刷怪半径必须大于 0。';
            }

            static::validateMonsterPoolRows($row['normal_monsters'] ?? [], 'normal', "difficulties.{$index}.normal_monsters", $errors);
            static::validateMonsterPoolRows($row['elite_monsters'] ?? [], 'elite', "difficulties.{$index}.elite_monsters", $errors);
            static::validateMonsterPoolRows($row['boss_monsters'] ?? [], 'boss', "difficulties.{$index}.boss_monsters", $errors);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function monsterOptions(string $kind): array
    {
        return Monster::query()
            ->where('is_enabled', true)
            ->where('kind', $kind)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Monster $monster): array => [$monster->id => (string) $monster->name])
            ->all();
    }

    public static function monsterName(?string $monsterId): string
    {
        $monsterId = trim((string) $monsterId);

        if ($monsterId === '') {
            return '';
        }

        return static::monsterMeta($monsterId)['name'] ?? '';
    }

    public static function difficultySummary(mixed $rows): string
    {
        $normalized = static::normalizeDifficulties($rows);
        $parts = [];

        foreach ($normalized as $row) {
            $parts[] = sprintf(
                '%s / 普怪%d / 精英%d / Boss%d',
                (string) ($row['difficulty_name'] ?? '难度'),
                count(is_array($row['normal_monsters'] ?? null) ? $row['normal_monsters'] : []),
                count(is_array($row['elite_monsters'] ?? null) ? $row['elite_monsters'] : []),
                count(is_array($row['boss_monsters'] ?? null) ? $row['boss_monsters'] : []),
            );
        }

        return implode('；', array_slice($parts, 0, 3));
    }

    /**
     * @return array{id:string,name:string,kind:string}|array{}
     */
    private static function monsterMeta(string $monsterId): array
    {
        if ($monsterId === '') {
            return [];
        }

        if (isset(static::$monsterMetaCache[$monsterId])) {
            return static::$monsterMetaCache[$monsterId];
        }

        $monster = Monster::query()
            ->where('id', $monsterId)
            ->first(['id', 'name', 'kind']);

        if (! $monster) {
            return static::$monsterMetaCache[$monsterId] = [];
        }

        return static::$monsterMetaCache[$monsterId] = [
            'id' => (string) $monster->id,
            'name' => (string) $monster->name,
            'kind' => (string) $monster->kind,
        ];
    }

    private static function normalizeDifficultyRow(array $row, int $index): array
    {
        return [
            'difficulty_name' => static::normalizeDifficultyName((string) ($row['difficulty_name'] ?? ''), $index),
            'recommended_power' => max(0, (int) ($row['recommended_power'] ?? 0)),
            'spawn_interval' => static::normalizeFloat($row['spawn_interval'] ?? 1.6, 1.6),
            'onscreen_limit' => max(1, (int) ($row['onscreen_limit'] ?? 10)),
            'spawn_radius' => max(1, (int) ($row['spawn_radius'] ?? 220)),
            'normal_monsters' => static::normalizeMonsterPool($row['normal_monsters'] ?? null, 'normal'),
            'elite_monsters' => static::normalizeMonsterPool($row['elite_monsters'] ?? null, 'elite'),
            'boss_monsters' => static::normalizeMonsterPool($row['boss_monsters'] ?? null, 'boss'),
            'elite_spawn_rule' => is_array($row['elite_spawn_rule'] ?? null) ? $row['elite_spawn_rule'] : null,
            'boss_spawn_rule' => is_array($row['boss_spawn_rule'] ?? null) ? $row['boss_spawn_rule'] : null,
        ];
    }

    private static function normalizeMonsterPool(mixed $rows, string $kind): array
    {
        $normalized = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $monsterId = trim((string) ($row['monster_id'] ?? $row['id'] ?? ''));
            $weight = max(0, (int) ($row['weight'] ?? $row['w'] ?? 0));

            if ($monsterId === '') {
                continue;
            }

            $normalized[] = [
                'monster_id' => $monsterId,
                'weight' => $weight,
            ];
        }

        if ($normalized === []) {
            return match ($kind) {
                'elite' => [['monster_id' => 'elite_a', 'weight' => 100]],
                'boss' => [['monster_id' => 'boss_a', 'weight' => 100]],
                default => [['monster_id' => 'mob_a', 'weight' => 100]],
            };
        }

        return array_values($normalized);
    }

    private static function validateMonsterPoolRows(mixed $rows, string $kind, string $fieldPath, array &$errors): void
    {
        $pool = is_array($rows) ? array_values($rows) : [];

        if ($pool === []) {
            $errors[$fieldPath] = '请至少配置一条怪物。';
            return;
        }

        $weightTotal = 0;

        foreach ($pool as $index => $row) {
            if (! is_array($row)) {
                $errors["{$fieldPath}.{$index}"] = '怪物配置格式错误。';
                continue;
            }

            $monsterId = trim((string) ($row['monster_id'] ?? ''));
            $weight = $row['weight'] ?? null;

            if ($monsterId === '') {
                $errors["{$fieldPath}.{$index}.monster_id"] = '请选择怪物。';
                continue;
            }

            $monsterMeta = static::monsterMeta($monsterId);
            if ($monsterMeta === []) {
                $errors["{$fieldPath}.{$index}.monster_id"] = '怪物不存在。';
                continue;
            }

            if (($monsterMeta['kind'] ?? '') !== $kind) {
                $errors["{$fieldPath}.{$index}.monster_id"] = sprintf(
                    '%s 不是%s怪物。',
                    $monsterMeta['name'] ?? $monsterId,
                    static::kindLabel($kind),
                );
            }

            if (! is_numeric($weight) || (int) $weight <= 0) {
                $errors["{$fieldPath}.{$index}.weight"] = '权重必须大于 0。';
                continue;
            }

            $weightTotal += (int) $weight;
        }

        if ($weightTotal <= 0) {
            $errors[$fieldPath] = '怪物池权重总和必须大于 0。';
        }
    }

    private static function normalizeDifficultyName(string $name, int $index): string
    {
        $name = trim($name);

        return $name !== '' ? $name : static::defaultDifficultyName($index);
    }

    private static function defaultDifficultyName(int $index): string
    {
        return $index === 0 ? '普通' : '难度' . ($index + 1);
    }

    private static function normalizeFloat(mixed $value, float $fallback): float
    {
        return is_numeric($value) ? max(0.1, round((float) $value, 4)) : $fallback;
    }

    private static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'elite' => '精英',
            'boss' => 'Boss',
            default => '普通',
        };
    }
}
