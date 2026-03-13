<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\File;

class BattleDefaultsService
{
    public const SETTING_KEY = 'battle_defaults';

    private const PROJECT_BATTLE_DEFAULTS_FILE = '../data/battle_defaults.json';

    public static function defaultConfig(): array
    {
        return [
            'spawn_rules' => [
                'elite_every_kills' => 40,
                'boss_every_kills' => 120,
            ],
            'drops' => [
                'drop_chance' => 0.28,
                'rarity_weights' => [
                    'white' => 85,
                    'blue' => 13,
                    'gold' => 2,
                ],
                'items_by_rarity' => [
                    'white' => ['桂枝', '玉屑'],
                    'blue' => ['白玉碎'],
                    'gold' => ['妖核'],
                ],
            ],
            'special_drops' => [
                'normal' => [
                    'extra_gem_chance' => 0.02,
                    'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                ],
                'elite' => [
                    'punch_stone_chance' => 0.15,
                    'extra_gem_chance' => 0.08,
                    'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                ],
                'boss' => [
                    'core_guarantee' => '妖王核心',
                    'punch_stone_chance' => 0.40,
                    'extra_gem_chance' => 0.22,
                    'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                ],
            ],
            'player_regen' => [
                'regen_delay' => 1.2,
                'regen_base' => 0.35,
                'regen_per_physique' => 0.02,
                'heal_on_kill' => [
                    'normal' => 1,
                    'elite' => 2,
                    'boss' => 4,
                ],
            ],
            'refine_effect_pool' => [
                ['w' => 40, 'type' => 'stat', 'stat' => 'ATK', 'val' => 1],
                ['w' => 30, 'type' => 'stat', 'stat' => 'DEF', 'val' => 1],
                ['w' => 20, 'type' => 'stat', 'stat' => 'HP', 'val' => 2],
                ['w' => 7, 'type' => 'stat', 'stat' => 'LOOT_BONUS_PERCENT', 'val' => 1],
                ['w' => 3, 'type' => 'skill_level', 'skill_id' => 'BING_01', 'val' => 1],
            ],
            'economy' => [
                'identify_cost_by_rarity' => [
                    'white' => 20,
                    'blue' => 60,
                    'gold' => 160,
                ],
                'refine_cost_by_rarity' => [
                    'white' => [
                        'gold' => 15,
                        'items' => ['玉屑' => 2],
                    ],
                    'blue' => [
                        'gold' => 40,
                        'items' => ['白玉碎' => 2],
                    ],
                    'gold' => [
                        'gold' => 100,
                        'items' => ['白玉碎' => 5, '妖核' => 1],
                    ],
                ],
                'salvage_reward_by_rarity' => [
                    'white' => [
                        'gold' => 8,
                        'items' => ['玉屑' => 1],
                    ],
                    'blue' => [
                        'gold' => 20,
                        'items' => ['白玉碎' => 1],
                    ],
                    'gold' => [
                        'gold' => 50,
                        'items' => ['白玉碎' => 2, '妖核' => 1],
                    ],
                ],
            ],
            'classes' => self::projectBattleDefaults()['classes'] ?? self::fallbackClasses(),
            'combat' => self::projectBattleDefaults()['combat'] ?? self::fallbackCombat(),
            'ai_profiles' => self::projectBattleDefaults()['ai_profiles'] ?? self::fallbackAiProfiles(),
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
        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function classOptions(): array
    {
        $rows = self::loadConfig()['classes'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }

            $options[$id] = $name;
        }

        return $options;
    }

    public static function classNameById(?string $classId): string
    {
        $classId = trim((string) $classId);
        if ($classId === '') {
            return '';
        }

        return self::classOptions()[$classId] ?? '';
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

    private static function projectBattleDefaults(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $path = base_path(self::PROJECT_BATTLE_DEFAULTS_FILE);
        if (! File::exists($path)) {
            return $cache = [];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return $cache = [];
        }

        $battle = $decoded['battle'] ?? [];

        return $cache = is_array($battle) ? $battle : [];
    }

    private static function fallbackClasses(): array
    {
        return [
            [
                'id' => 'bing',
                'name' => '兵宗',
                'role' => '武技派（不绑定武器）',
                'starter' => [
                    'level' => 1,
                    'free_attribute_points' => 0,
                    'skill_points' => 1,
                    'base_attributes' => [
                        'strength' => 0,
                        'physique' => 0,
                        'agility' => 0,
                        'spirit' => 0,
                        'true_energy' => 0,
                        'fortune' => 0,
                    ],
                ],
                'base_combat' => [
                    'move_speed' => 1.0,
                    'attack_range' => 1.8,
                    'basic_attack_cd' => 1.2,
                    'basic_attack_damage' => ['source' => 'WD', 'coef' => 0.7],
                    'notes' => '近战；基础攻击在技能空档自动执行。',
                ],
            ],
            [
                'id' => 'vajra',
                'name' => '金刚宗',
                'role' => '硬抗派（护盾/结界/反噬/震慑）',
                'starter' => [
                    'level' => 1,
                    'free_attribute_points' => 0,
                    'skill_points' => 1,
                    'base_attributes' => [
                        'strength' => 0,
                        'physique' => 0,
                        'agility' => 0,
                        'spirit' => 0,
                        'true_energy' => 0,
                        'fortune' => 0,
                    ],
                ],
                'base_combat' => [
                    'move_speed' => 0.95,
                    'attack_range' => 1.7,
                    'basic_attack_cd' => 1.3,
                    'basic_attack_damage' => ['source' => 'WD', 'coef' => 0.65],
                    'notes' => '近战；偏稳推。',
                ],
            ],
            [
                'id' => 'talisman',
                'name' => '符箓宗',
                'role' => '术法派（连锁/灼烧/控制/召唤）',
                'starter' => [
                    'level' => 1,
                    'free_attribute_points' => 0,
                    'skill_points' => 1,
                    'base_attributes' => [
                        'strength' => 0,
                        'physique' => 0,
                        'agility' => 0,
                        'spirit' => 0,
                        'true_energy' => 0,
                        'fortune' => 0,
                    ],
                ],
                'base_combat' => [
                    'move_speed' => 1.0,
                    'attack_range' => 4.8,
                    'basic_attack_cd' => 1.25,
                    'basic_attack_damage' => ['source' => 'SP', 'coef' => 0.7],
                    'notes' => '远程；保持距离施法。',
                ],
            ],
        ];
    }

    private static function fallbackCombat(): array
    {
        return [
            'gcd_seconds' => 0.4,
            'default_skill_coef_per_level' => 0.03,
            'avoid_waste' => [
                'debuff_refresh_threshold_sec' => 2.0,
                'shield_keep_threshold_pct' => 0.3,
            ],
        ];
    }

    private static function fallbackAiProfiles(): array
    {
        return [
            'profiles' => [],
            'auto_switch' => [
                'rules_in_order' => [],
                'switch_cooldown_sec' => 2.0,
            ],
        ];
    }
}
