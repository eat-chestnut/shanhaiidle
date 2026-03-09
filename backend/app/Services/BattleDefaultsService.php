<?php

namespace App\Services;

use App\Models\AppSetting;

class BattleDefaultsService
{
    public const SETTING_KEY = 'battle_defaults';

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
