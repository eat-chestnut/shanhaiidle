<?php

namespace App\Services;

use App\Models\AppSetting;

class EquipmentGrowthRulesService
{
    public const SETTING_KEY = 'equipment_growth_rules';

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
            'star_material_stage' => [
                '1_3' => '初阶星材',
                '4_6' => '中阶星材',
                '7_8' => '高阶星材',
                '9_10' => '极阶星材',
            ],
            'socket_unlocks' => [
                '3' => 1,
                '6' => 2,
                '8' => 3,
                '10' => 4,
            ],
            'blue_affix_unlock_level' => 30,
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
        if (AppSetting::query()->where('key', self::SETTING_KEY)->exists()) {
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
