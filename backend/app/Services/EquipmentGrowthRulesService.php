<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Item;
use Illuminate\Validation\ValidationException;

class EquipmentGrowthRulesService
{
    public const SETTING_KEY = 'equipment_growth_rules';

    private const BLUE_AFFIX_UNLOCK_LEVEL = 1;

    public static function defaultConfig(): array
    {
        return [
            'set_stage_piece_count' => [
                '20' => 4,
                '40' => 6,
                '60' => 8,
            ],
            'star_caps' => [
                '20' => 3,
                '40' => 6,
                '50' => 8,
                '60' => 10,
            ],
            'star_material_stage' => self::defaultStarMaterialStages(),
            'socket_unlocks' => self::fixedSocketUnlocks(),
            'blue_affix_unlock_level' => self::BLUE_AFFIX_UNLOCK_LEVEL,
            'purple_affix_unlock_level' => 50,
            'resonance_thresholds' => [3, 6, 8, 10],
            'blue_gear_rules' => [
                'drop_from_boss_only' => true,
                'allowed_slots' => ['main_weapon', 'off_weapon', 'armor', 'belt', 'shoes', 'gloves', 'helm', 'necklace'],
                'forbidden_slots' => ['ring', 'bracelet', 'talisman'],
                'can_star_up' => false,
                'can_rank_up' => false,
                'can_socket' => false,
                'can_reforge' => false,
            ],
            'main_equipment_limits' => [
                'ring' => 2,
                'bracelet' => 2,
            ],
        ];
    }

    public static function ensureDefaultSetting(): void
    {
        $raw = AppSetting::getValue(self::SETTING_KEY);

        if ($raw !== null && trim($raw) !== '') {
            self::syncLegacyBlueAffixUnlockLevel($raw);

            return;
        }

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode(self::defaultConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function loadConfig(): array
    {
        $raw = AppSetting::getValue(self::SETTING_KEY);
        if ($raw === null || trim($raw) === '') {
            return self::defaultConfig();
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return self::defaultConfig();
        }

        $config = self::mergeRecursive(self::defaultConfig(), $decoded);
        $config['socket_unlocks'] = self::fixedSocketUnlocks();
        $config['star_material_stage'] = self::normalizeEditableStarMaterialStages($config['star_material_stage'] ?? null);

        return $config;
    }

    public static function saveConfig(array $config): void
    {
        $config['socket_unlocks'] = self::fixedSocketUnlocks();
        $config['star_material_stage'] = self::validateAndNormalizeStarMaterialStages(
            $config['star_material_stage'] ?? [],
            $config['star_caps'] ?? [],
            'data.star_material_stage',
        );

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function exportConfig(): array
    {
        $config = self::loadConfig();
        $rows = self::validateAndNormalizeStarMaterialStages(
            $config['star_material_stage'] ?? [],
            $config['star_caps'] ?? [],
            'star_material_stage',
        );

        $materialNames = self::starMaterialItemMap();
        $config['star_material_stage'] = array_map(
            fn (array $row): array => $row + [
                'material_name' => $materialNames[$row['material_id']] ?? '',
            ],
            $rows,
        );

        return $config;
    }

    public static function defaultStarMaterialStages(): array
    {
        return [
            [
                'star_from' => 1,
                'star_to' => 3,
                'material_id' => 'star_stone_t1_common',
                'material_count' => 1,
                'sort' => 1,
            ],
            [
                'star_from' => 4,
                'star_to' => 6,
                'material_id' => 'star_stone_t2_common',
                'material_count' => 1,
                'sort' => 2,
            ],
            [
                'star_from' => 7,
                'star_to' => 8,
                'material_id' => 'star_stone_t3_common',
                'material_count' => 1,
                'sort' => 3,
            ],
            [
                'star_from' => 9,
                'star_to' => 10,
                'material_id' => 'star_stone_t4_common',
                'material_count' => 1,
                'sort' => 4,
            ],
        ];
    }

    public static function fixedSocketUnlocks(): array
    {
        return [
            '3' => 1,
            '6' => 2,
            '8' => 3,
            '10' => 4,
        ];
    }

    private static function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                if (self::isList($value) || self::isList($base[$key])) {
                    $base[$key] = array_values($value);
                } else {
                    $base[$key] = self::mergeRecursive($base[$key], $value);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return array_values($value) === $value;
    }

    private static function normalizeEditableStarMaterialStages(mixed $rows): array
    {
        if (! is_array($rows) || ! self::isList($rows)) {
            return self::defaultStarMaterialStages();
        }

        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                return self::defaultStarMaterialStages();
            }

            $materialId = trim((string) ($row['material_id'] ?? ''));
            if ($materialId === '') {
                return self::defaultStarMaterialStages();
            }

            $normalized[] = [
                'star_from' => (int) ($row['star_from'] ?? 0),
                'star_to' => (int) ($row['star_to'] ?? 0),
                'material_id' => $materialId,
                'material_count' => (int) ($row['material_count'] ?? 1),
                'sort' => $index + 1,
            ];
        }

        return $normalized;
    }

    private static function validateAndNormalizeStarMaterialStages(array $rows, array $starCaps, string $errorPrefix): array
    {
        if (! self::isList($rows)) {
            throw ValidationException::withMessages([
                $errorPrefix => '升星材料阶段必须按数组行保存，不能再使用阶段区间字符串。',
            ]);
        }

        $availableMaterials = self::starMaterialItemMap();
        $maxStar = self::resolveMaxStarCap($starCaps);
        $normalized = [];
        $errors = [];

        if ($rows === []) {
            $errors[$errorPrefix] = '至少需要配置一条升星材料阶段。';
        }

        foreach (array_values($rows) as $index => $row) {
            $rowPrefix = "{$errorPrefix}.{$index}";

            if (! is_array($row)) {
                $errors[$rowPrefix] = '升星材料阶段配置项格式错误。';
                continue;
            }

            $starFrom = filter_var($row['star_from'] ?? null, FILTER_VALIDATE_INT);
            $starTo = filter_var($row['star_to'] ?? null, FILTER_VALIDATE_INT);
            $materialId = trim((string) ($row['material_id'] ?? ''));
            $materialCount = filter_var($row['material_count'] ?? null, FILTER_VALIDATE_INT);

            if ($starFrom === false || $starFrom < 1) {
                $errors["{$rowPrefix}.star_from"] = '起始星级必须为正整数。';
            }

            if ($starTo === false || $starTo < 1) {
                $errors["{$rowPrefix}.star_to"] = '结束星级必须为正整数。';
            }

            if ($starFrom !== false && $starTo !== false && $starFrom > $starTo) {
                $errors["{$rowPrefix}.star_to"] = '结束星级必须大于或等于起始星级。';
            }

            if ($starFrom !== false && $starFrom > $maxStar) {
                $errors["{$rowPrefix}.star_from"] = sprintf('起始星级不能超过当前系统上限 %d。', $maxStar);
            }

            if ($starTo !== false && $starTo > $maxStar) {
                $errors["{$rowPrefix}.star_to"] = sprintf('结束星级不能超过当前系统上限 %d。', $maxStar);
            }

            if ($materialId === '') {
                $errors["{$rowPrefix}.material_id"] = '请选择升星材料。';
            } elseif (! array_key_exists($materialId, $availableMaterials)) {
                $errors["{$rowPrefix}.material_id"] = '所选升星材料不存在或未启用。';
            }

            if ($materialCount === false || $materialCount < 1) {
                $errors["{$rowPrefix}.material_count"] = '消耗数量必须大于或等于 1。';
            }

            if (isset($errors["{$rowPrefix}.star_from"]) || isset($errors["{$rowPrefix}.star_to"]) || isset($errors["{$rowPrefix}.material_id"]) || isset($errors["{$rowPrefix}.material_count"])) {
                continue;
            }

            $normalized[] = [
                'star_from' => (int) $starFrom,
                'star_to' => (int) $starTo,
                'material_id' => $materialId,
                'material_count' => (int) $materialCount,
                'sort' => $index + 1,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($normalized as $index => $current) {
            foreach ($normalized as $otherIndex => $other) {
                if ($otherIndex <= $index) {
                    continue;
                }

                $overlaps = max($current['star_from'], $other['star_from']) <= min($current['star_to'], $other['star_to']);
                if (! $overlaps) {
                    continue;
                }

                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}.star_from" => sprintf('第 %d 行与第 %d 行的星级区间重叠。', $index + 1, $otherIndex + 1),
                    "{$errorPrefix}.{$otherIndex}.star_from" => sprintf('第 %d 行与第 %d 行的星级区间重叠。', $otherIndex + 1, $index + 1),
                ]);
            }
        }

        return $normalized;
    }

    private static function resolveMaxStarCap(array $starCaps): int
    {
        $maxStar = 0;

        foreach ($starCaps as $value) {
            $maxStar = max($maxStar, (int) $value);
        }

        return max($maxStar, 1);
    }

    private static function starMaterialItemMap(): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->where('type', 'material')
            ->where('material_type', 'star')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function syncLegacyBlueAffixUnlockLevel(string $raw): void
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            AppSetting::setValue(
                self::SETTING_KEY,
                json_encode(self::defaultConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );

            return;
        }

        $current = (int) ($decoded['blue_affix_unlock_level'] ?? self::BLUE_AFFIX_UNLOCK_LEVEL);
        if ($current === self::BLUE_AFFIX_UNLOCK_LEVEL) {
            return;
        }

        $decoded['blue_affix_unlock_level'] = self::BLUE_AFFIX_UNLOCK_LEVEL;

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
