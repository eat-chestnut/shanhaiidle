<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Validation\ValidationException;

class CharacterGrowthRulesService
{
    public const SETTING_KEY = 'character_growth_rules';

    /**
     * @var array<string, string>
     */
    private const CLASS_OPTIONS = [
        'bing' => '兵宗',
        'vajra' => '金刚宗',
        'talisman' => '符箓宗',
    ];

    /**
     * @var array<int, int>
     */
    private const DEFAULT_EXP_BY_LEVEL = [
        1 => 12,
        2 => 16,
        3 => 20,
        4 => 24,
        5 => 28,
        6 => 32,
        7 => 36,
        8 => 40,
        9 => 45,
        10 => 50,
        11 => 55,
        12 => 60,
        13 => 66,
        14 => 72,
        15 => 78,
        16 => 84,
        17 => 90,
        18 => 97,
        19 => 104,
        20 => 112,
        21 => 120,
        22 => 128,
        23 => 136,
        24 => 145,
        25 => 154,
        26 => 163,
        27 => 172,
        28 => 182,
        29 => 192,
        30 => 202,
        31 => 213,
        32 => 224,
        33 => 235,
        34 => 247,
        35 => 259,
        36 => 271,
        37 => 284,
        38 => 297,
        39 => 310,
        40 => 324,
        41 => 338,
        42 => 352,
        43 => 367,
        44 => 382,
        45 => 398,
        46 => 414,
        47 => 430,
        48 => 447,
        49 => 464,
        50 => 482,
        51 => 500,
        52 => 519,
        53 => 538,
        54 => 557,
        55 => 577,
        56 => 598,
        57 => 619,
        58 => 640,
        59 => 662,
    ];

    public static function classOptions(): array
    {
        return self::CLASS_OPTIONS;
    }

    public static function defaultConfig(): array
    {
        return [
            'level_cap' => 60,
            'initial' => [
                'level' => 1,
                'free_attr_points' => 0,
                'skill_points' => 1,
                'current_class' => 'bing',
                'base_attributes' => self::defaultBaseAttributes(),
            ],
            'level_exp_table' => self::defaultLevelExpTable(),
            'base_growth' => [
                'hp' => [
                    'base' => 10,
                    'per_level_every' => 4,
                    'per_level_gain' => 1,
                ],
                'qi' => [
                    'base' => 10,
                    'per_level_every' => 5,
                    'per_level_gain' => 1,
                ],
                'atk' => [
                    'base' => 1,
                    'per_level_gain' => 0,
                ],
                'def' => [
                    'base' => 0,
                    'per_level_gain' => 0,
                ],
                'crit_percent' => [
                    'base' => 5,
                ],
                'loot_bonus_percent' => [
                    'base' => 0,
                ],
            ],
            'attribute_formulas' => [
                'physique' => [
                    'hp_per_point' => 1.0,
                    'hp_extra_every_10' => 1,
                    'def_per_point' => 0.0,
                ],
                'true_energy' => [
                    'qi_per_point' => 1.0,
                ],
                'agility' => [
                    'crit_percent_per_point' => 0.25,
                    'dodge_per_point' => 0.0,
                    'attack_speed_per_point' => 0.0,
                ],
                'strength' => [
                    'phys_mul_permille_per_point' => 12,
                ],
                'spirit' => [
                    'spell_mul_permille_per_point' => 12,
                ],
                'fortune' => [
                    'loot_bonus_percent_per_point' => 0.6,
                ],
            ],
        ];
    }

    public static function ensureDefaultSetting(): void
    {
        $exists = AppSetting::query()->where('key', self::SETTING_KEY)->exists();
        if ($exists) {
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

        return self::mergeRecursive(self::defaultConfig(), $decoded);
    }

    public static function saveConfig(array $config): void
    {
        $normalized = self::validateAndNormalizeConfig($config);

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function exportConfig(): array
    {
        return self::validateAndNormalizeConfig(self::loadConfig());
    }

    /**
     * @return array<int, array{level:int, exp_to_next:int, attr_points_gain:int}>
     */
    private static function defaultLevelExpTable(): array
    {
        $rows = [];

        foreach (self::DEFAULT_EXP_BY_LEVEL as $level => $expToNext) {
            $rows[] = [
                'level' => $level,
                'exp_to_next' => $expToNext,
                'attr_points_gain' => 1,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    private static function defaultBaseAttributes(): array
    {
        return [
            'strength' => 0,
            'physique' => 0,
            'agility' => 0,
            'spirit' => 0,
            'true_energy' => 0,
            'fortune' => 0,
        ];
    }

    private static function validateAndNormalizeConfig(array $config): array
    {
        $normalized = self::mergeRecursive(self::defaultConfig(), $config);
        $errors = [];

        $levelCap = self::normalizeInt($normalized['level_cap'] ?? null, 'level_cap', $errors, 20);
        $initial = is_array($normalized['initial'] ?? null) ? $normalized['initial'] : [];

        $initialLevel = self::normalizeInt($initial['level'] ?? null, 'initial.level', $errors, 1);
        if ($initialLevel > $levelCap) {
            $errors['initial.level'] = '初始等级不能超过等级上限。';
        }

        $initialFreeAttrPoints = self::normalizeInt($initial['free_attr_points'] ?? null, 'initial.free_attr_points', $errors, 0);
        $initialSkillPoints = self::normalizeInt($initial['skill_points'] ?? null, 'initial.skill_points', $errors, 0);
        $initialClass = trim((string) ($initial['current_class'] ?? ''));
        if (! array_key_exists($initialClass, self::CLASS_OPTIONS)) {
            $errors['initial.current_class'] = '初始宗门必须从兵宗、金刚宗、符箓宗中选择。';
        }

        $baseAttributes = self::normalizeBaseAttributes(
            $initial['base_attributes'] ?? [],
            'initial.base_attributes',
            $errors,
        );

        $levelExpTable = self::normalizeLevelExpTable(
            $normalized['level_exp_table'] ?? [],
            $levelCap,
            $errors,
        );

        $baseGrowth = self::normalizeBaseGrowth(
            $normalized['base_growth'] ?? [],
            'base_growth',
            $errors,
        );

        $attributeFormulas = self::normalizeAttributeFormulas(
            $normalized['attribute_formulas'] ?? [],
            'attribute_formulas',
            $errors,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'level_cap' => $levelCap,
            'initial' => [
                'level' => $initialLevel,
                'free_attr_points' => $initialFreeAttrPoints,
                'skill_points' => $initialSkillPoints,
                'current_class' => $initialClass,
                'base_attributes' => $baseAttributes,
            ],
            'level_exp_table' => $levelExpTable,
            'base_growth' => $baseGrowth,
            'attribute_formulas' => $attributeFormulas,
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $source
     * @param  array<string, string>  $errors
     * @return array<string, int>
     */
    private static function normalizeBaseAttributes(mixed $source, string $prefix, array &$errors): array
    {
        $rows = is_array($source) ? $source : [];
        $normalized = [];

        foreach (array_keys(self::defaultBaseAttributes()) as $key) {
            $normalized[$key] = self::normalizeInt($rows[$key] ?? null, "{$prefix}.{$key}", $errors, 0);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|mixed  $source
     * @param  array<string, string>  $errors
     * @return array<int, array{level:int, exp_to_next:int, attr_points_gain:int}>
     */
    private static function normalizeLevelExpTable(mixed $source, int $levelCap, array &$errors): array
    {
        if (! is_array($source) || ! self::isList($source)) {
            $errors['level_exp_table'] = '等级经验表必须按数组行保存。';

            return self::defaultLevelExpTable();
        }

        $rows = array_values($source);
        $normalized = [];
        $expectedLastLevel = max(1, $levelCap - 1);

        if (count($rows) !== $expectedLastLevel) {
            $errors['level_exp_table'] = sprintf('等级经验表必须正好配置 1 到 %d 级。', $expectedLastLevel);
        }

        foreach ($rows as $index => $row) {
            $prefix = "level_exp_table.{$index}";
            if (! is_array($row)) {
                $errors[$prefix] = '等级经验配置项格式错误。';
                continue;
            }

            $expectedLevel = $index + 1;
            $level = self::normalizeInt($row['level'] ?? null, "{$prefix}.level", $errors, 1);
            $expToNext = self::normalizeInt($row['exp_to_next'] ?? null, "{$prefix}.exp_to_next", $errors, 1);
            $attrPointsGain = self::normalizeInt($row['attr_points_gain'] ?? null, "{$prefix}.attr_points_gain", $errors, 0);

            if ($level !== $expectedLevel) {
                $errors["{$prefix}.level"] = sprintf('等级经验表必须从 1 开始连续递增，第 %d 行应为等级 %d。', $index + 1, $expectedLevel);
            }

            $normalized[] = [
                'level' => $level,
                'exp_to_next' => $expToNext,
                'attr_points_gain' => $attrPointsGain,
            ];
        }

        if ($normalized !== []) {
            $lastLevel = (int) ($normalized[array_key_last($normalized)]['level'] ?? 0);
            if ($lastLevel !== $expectedLastLevel) {
                $errors['level_exp_table'] = sprintf('等级经验表最后一级必须是 %d。', $expectedLastLevel);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|mixed  $source
     * @param  array<string, string>  $errors
     * @return array<string, array<string, int|float>>
     */
    private static function normalizeBaseGrowth(mixed $source, string $prefix, array &$errors): array
    {
        $rows = is_array($source) ? $source : [];

        return [
            'hp' => [
                'base' => self::normalizeInt(data_get($rows, 'hp.base'), "{$prefix}.hp.base", $errors, 0),
                'per_level_every' => self::normalizeInt(data_get($rows, 'hp.per_level_every'), "{$prefix}.hp.per_level_every", $errors, 1),
                'per_level_gain' => self::normalizeInt(data_get($rows, 'hp.per_level_gain'), "{$prefix}.hp.per_level_gain", $errors, 0),
            ],
            'qi' => [
                'base' => self::normalizeInt(data_get($rows, 'qi.base'), "{$prefix}.qi.base", $errors, 0),
                'per_level_every' => self::normalizeInt(data_get($rows, 'qi.per_level_every'), "{$prefix}.qi.per_level_every", $errors, 1),
                'per_level_gain' => self::normalizeInt(data_get($rows, 'qi.per_level_gain'), "{$prefix}.qi.per_level_gain", $errors, 0),
            ],
            'atk' => [
                'base' => self::normalizeInt(data_get($rows, 'atk.base'), "{$prefix}.atk.base", $errors, 0),
                'per_level_gain' => self::normalizeInt(data_get($rows, 'atk.per_level_gain'), "{$prefix}.atk.per_level_gain", $errors, 0),
            ],
            'def' => [
                'base' => self::normalizeInt(data_get($rows, 'def.base'), "{$prefix}.def.base", $errors, 0),
                'per_level_gain' => self::normalizeInt(data_get($rows, 'def.per_level_gain'), "{$prefix}.def.per_level_gain", $errors, 0),
            ],
            'crit_percent' => [
                'base' => self::normalizeInt(data_get($rows, 'crit_percent.base'), "{$prefix}.crit_percent.base", $errors, 0),
            ],
            'loot_bonus_percent' => [
                'base' => self::normalizeInt(data_get($rows, 'loot_bonus_percent.base'), "{$prefix}.loot_bonus_percent.base", $errors, 0),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $source
     * @param  array<string, string>  $errors
     * @return array<string, array<string, int|float>>
     */
    private static function normalizeAttributeFormulas(mixed $source, string $prefix, array &$errors): array
    {
        $rows = is_array($source) ? $source : [];

        return [
            'physique' => [
                'hp_per_point' => self::normalizeNumber(data_get($rows, 'physique.hp_per_point'), "{$prefix}.physique.hp_per_point", $errors, 0),
                'hp_extra_every_10' => self::normalizeInt(data_get($rows, 'physique.hp_extra_every_10'), "{$prefix}.physique.hp_extra_every_10", $errors, 0),
                'def_per_point' => self::normalizeNumber(data_get($rows, 'physique.def_per_point'), "{$prefix}.physique.def_per_point", $errors, 0),
            ],
            'true_energy' => [
                'qi_per_point' => self::normalizeNumber(data_get($rows, 'true_energy.qi_per_point'), "{$prefix}.true_energy.qi_per_point", $errors, 0),
            ],
            'agility' => [
                'crit_percent_per_point' => self::normalizeNumber(data_get($rows, 'agility.crit_percent_per_point'), "{$prefix}.agility.crit_percent_per_point", $errors, 0),
                'dodge_per_point' => self::normalizeNumber(data_get($rows, 'agility.dodge_per_point'), "{$prefix}.agility.dodge_per_point", $errors, 0),
                'attack_speed_per_point' => self::normalizeNumber(data_get($rows, 'agility.attack_speed_per_point'), "{$prefix}.agility.attack_speed_per_point", $errors, 0),
            ],
            'strength' => [
                'phys_mul_permille_per_point' => self::normalizeInt(data_get($rows, 'strength.phys_mul_permille_per_point'), "{$prefix}.strength.phys_mul_permille_per_point", $errors, 0),
            ],
            'spirit' => [
                'spell_mul_permille_per_point' => self::normalizeInt(data_get($rows, 'spirit.spell_mul_permille_per_point'), "{$prefix}.spirit.spell_mul_permille_per_point", $errors, 0),
            ],
            'fortune' => [
                'loot_bonus_percent_per_point' => self::normalizeNumber(data_get($rows, 'fortune.loot_bonus_percent_per_point'), "{$prefix}.fortune.loot_bonus_percent_per_point", $errors, 0),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $errors
     */
    private static function normalizeInt(mixed $value, string $field, array &$errors, int $min): int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false || $normalized < $min) {
            $errors[$field] = sprintf('%s 必须是大于或等于 %d 的整数。', $field, $min);

            return max($min, 0);
        }

        return (int) $normalized;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private static function normalizeNumber(mixed $value, string $field, array &$errors, float $min): float
    {
        if (! is_numeric($value)) {
            $errors[$field] = sprintf('%s 必须是大于或等于 %s 的数值。', $field, rtrim(rtrim((string) $min, '0'), '.'));

            return max($min, 0);
        }

        $normalized = (float) $value;
        if ($normalized < $min) {
            $errors[$field] = sprintf('%s 必须是大于或等于 %s 的数值。', $field, rtrim(rtrim((string) $min, '0'), '.'));

            return max($min, 0);
        }

        return $normalized;
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
}
