<?php

namespace Database\Seeders;

use App\Models\BlueAffix;
use Illuminate\Database\Seeder;

class BlueAffixPoolSeeder extends Seeder
{
    private const LEVELS = [1, 5, 10, 15, 20];

    private const AFFIX_DEFS = [
        [
            'id_prefix' => 'blue_atk_flat',
            'name' => '锐击',
            'stat' => 'ATK',
            'slot_tags' => ['main_weapon', 'off_weapon', 'gloves', 'necklace'],
            'value_mode' => 'flat',
            'ranges' => [1 => [2, 4], 5 => [4, 7], 10 => [7, 11], 15 => [10, 15], 20 => [14, 20]],
            'notes' => '前期基础攻击词条，适用于武器、护手与输出项链。',
        ],
        [
            'id_prefix' => 'blue_hp_flat',
            'name' => '生息',
            'stat' => 'HP',
            'slot_tags' => ['armor', 'belt', 'shoes', 'helm', 'necklace'],
            'value_mode' => 'flat',
            'ranges' => [1 => [18, 32], 5 => [30, 52], 10 => [48, 80], 15 => [68, 108], 20 => [90, 140]],
            'notes' => '前期基础生命词条，覆盖防具与生存位项链。',
        ],
        [
            'id_prefix' => 'blue_def_flat',
            'name' => '坚壁',
            'stat' => 'DEF',
            'slot_tags' => ['armor', 'belt', 'shoes', 'helm', 'off_weapon'],
            'value_mode' => 'flat',
            'ranges' => [1 => [1, 3], 5 => [3, 5], 10 => [5, 8], 15 => [7, 11], 20 => [9, 14]],
            'notes' => '前期基础防御词条，适用于副武器与防具位。',
        ],
        [
            'id_prefix' => 'blue_crit_pct',
            'name' => '破势',
            'stat' => 'CRIT_PERCENT',
            'slot_tags' => ['main_weapon', 'off_weapon', 'gloves', 'necklace'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 1], 5 => [1, 2], 10 => [2, 3], 15 => [3, 4], 20 => [4, 5]],
            'notes' => '前期暴击率词条，面向输出型武器与护手。',
        ],
        [
            'id_prefix' => 'blue_crit_dmg',
            'name' => '追命',
            'stat' => 'CRIT_DMG',
            'slot_tags' => ['main_weapon', 'gloves', 'necklace'],
            'value_mode' => 'percent',
            'ranges' => [1 => [2, 3], 5 => [3, 4], 10 => [4, 6], 15 => [5, 7], 20 => [6, 8]],
            'notes' => '前期暴击伤害词条，作为输出项链和武器的高价值候选。',
        ],
        [
            'id_prefix' => 'blue_dodge',
            'name' => '轻影',
            'stat' => 'DODGE',
            'slot_tags' => ['shoes', 'belt', 'necklace'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 1], 5 => [1, 2], 10 => [2, 3], 15 => [3, 4], 20 => [4, 5]],
            'notes' => '前期闪避词条，主要出现在鞋子、腰带和项链。',
        ],
        [
            'id_prefix' => 'blue_atk_speed',
            'name' => '迅切',
            'stat' => 'ATK_SPEED',
            'slot_tags' => ['main_weapon', 'gloves', 'necklace'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 1], 5 => [1, 2], 10 => [2, 3], 15 => [3, 4], 20 => [4, 5]],
            'notes' => '前期攻速词条，面向高频输出蓝装。',
        ],
        [
            'id_prefix' => 'blue_melee_atk',
            'name' => '近战专精',
            'stat' => 'MELEE_ATK',
            'slot_tags' => ['main_weapon', 'off_weapon', 'gloves'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 2], 5 => [2, 3], 10 => [3, 4], 15 => [4, 6], 20 => [5, 7]],
            'notes' => '前期近战倍率词条，仅出现在近战相关部位。',
        ],
        [
            'id_prefix' => 'blue_spell_atk',
            'name' => '术法专精',
            'stat' => 'SPELL_ATK',
            'slot_tags' => ['main_weapon', 'off_weapon', 'necklace'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 2], 5 => [2, 3], 10 => [3, 4], 15 => [4, 6], 20 => [5, 7]],
            'notes' => '前期术法倍率词条，适用于法系武器、副手和项链。',
        ],
        [
            'id_prefix' => 'blue_pdef',
            'name' => '铁躯',
            'stat' => 'PDEF',
            'slot_tags' => ['armor', 'belt', 'shoes', 'helm'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 2], 5 => [2, 3], 10 => [3, 4], 15 => [4, 6], 20 => [5, 7]],
            'notes' => '前期物理防御词条，主要用于护甲系蓝装。',
        ],
        [
            'id_prefix' => 'blue_mdef',
            'name' => '灵壁',
            'stat' => 'MDEF',
            'slot_tags' => ['armor', 'belt', 'helm', 'necklace'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 2], 5 => [2, 3], 10 => [3, 4], 15 => [4, 6], 20 => [5, 7]],
            'notes' => '前期法术防御词条，用于头盔、防具与项链。',
        ],
        [
            'id_prefix' => 'blue_cdr',
            'name' => '归息',
            'stat' => 'CDR',
            'slot_tags' => ['off_weapon', 'necklace', 'helm'],
            'value_mode' => 'percent',
            'ranges' => [1 => [1, 1], 5 => [1, 2], 10 => [2, 3], 15 => [3, 4], 20 => [4, 5]],
            'notes' => '前期冷却缩减词条，供法系和功能位使用。',
        ],
    ];

    public function run(): void
    {
        $rows = [];
        $sortOrder = 10;

        foreach (self::LEVELS as $level) {
            foreach (self::AFFIX_DEFS as $definition) {
                [$minValue, $maxValue] = $definition['ranges'][$level];

                $rows[] = $this->affix(
                    sprintf('%s_lv%d', $definition['id_prefix'], $level),
                    sprintf('%s·%d级', $definition['name'], $level),
                    $definition['stat'],
                    $definition['slot_tags'],
                    $minValue,
                    $maxValue,
                    $definition['value_mode'],
                    $level,
                    $sortOrder,
                    sprintf('%s（%d级档）', $definition['notes'], $level),
                );

                $sortOrder += 10;
            }
        }

        $validIds = [];

        foreach ($rows as $row) {
            $validIds[] = $row['affix_id'];
            BlueAffix::query()->updateOrCreate(
                ['affix_id' => $row['affix_id']],
                $row,
            );
        }

        BlueAffix::query()->whereNotIn('affix_id', $validIds)->delete();
    }

    private function affix(
        string $affixId,
        string $affixName,
        string $stat,
        array $slotTags,
        int $minValue,
        int $maxValue,
        string $valueMode,
        int $unlockLevel,
        int $sortOrder,
        string $notes,
    ): array {
        return [
            'affix_id' => $affixId,
            'affix_name' => $affixName,
            'stat' => $stat,
            'slot_tags' => $slotTags,
            'flow_tags' => [],
            'min_value' => $minValue,
            'max_value' => $maxValue,
            'value_mode' => $valueMode,
            'weight' => 1,
            'unlock_level' => $unlockLevel,
            'sort_order' => $sortOrder,
            'notes' => $notes,
            'is_enabled' => true,
        ];
    }
}
