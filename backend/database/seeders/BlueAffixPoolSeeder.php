<?php

namespace Database\Seeders;

use App\Models\BlueAffix;
use Illuminate\Database\Seeder;

class BlueAffixPoolSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->affix('blue_atk_flat_01', '锐击', 'ATK', ['main_weapon', 'off_weapon', 'gloves', 'ring', 'necklace'], 6, 16, 'flat', 30, 10, '通用攻击提升词条，适合作为主武器与输出饰品的基础词条。'),
            $this->affix('blue_hp_flat_01', '生息', 'HP', ['armor', 'belt', 'shoes', 'helm', 'necklace', 'bracelet', 'talisman'], 40, 120, 'flat', 30, 20, '通用生命提升词条，适合作为生存向蓝装的底层池。'),
            $this->affix('blue_def_flat_01', '坚壁', 'DEF', ['armor', 'belt', 'shoes', 'helm', 'bracelet'], 6, 16, 'flat', 30, 30, '通用防御提升词条，适合作为防御位蓝装词条。'),
            $this->affix('blue_crit_pct_01', '破势', 'CRIT_PERCENT', ['main_weapon', 'off_weapon', 'ring', 'necklace', 'gloves'], 1, 4, 'percent', 30, 40, '偏输出的暴击率词条，适合武器、手部和输出饰品。'),
            $this->affix('blue_crit_dmg_01', '追命', 'CRIT_DMG', ['main_weapon', 'ring', 'bracelet', 'necklace'], 2, 6, 'percent', 30, 50, '通用暴击伤害词条，用于蓝装中后期输出构筑。'),
            $this->affix('blue_melee_atk_01', '近战专精', 'MELEE_ATK', ['main_weapon', 'off_weapon', 'gloves', 'ring'], 2, 6, 'percent', 30, 60, '近战向倍率词条，可由模板权重决定是否偏向狂战或防战。'),
            $this->affix('blue_ranged_atk_01', '远袭专精', 'RANGED_ATK', ['main_weapon', 'off_weapon', 'ring', 'necklace'], 2, 6, 'percent', 30, 70, '远程向倍率词条，由模板控制抽取倾向。'),
            $this->affix('blue_spell_atk_01', '术法专精', 'SPELL_ATK', ['main_weapon', 'off_weapon', 'ring', 'necklace', 'talisman'], 2, 6, 'percent', 30, 80, '术法向倍率词条，由模板决定火法/冰法倾向。'),
            $this->affix('blue_dodge_01', '轻影', 'DODGE', ['shoes', 'belt', 'bracelet', 'necklace'], 1, 4, 'percent', 30, 90, '通用闪避词条，适合鞋子、腰带与灵巧饰品。'),
            $this->affix('blue_atk_speed_01', '迅切', 'ATK_SPEED', ['gloves', 'ring', 'necklace', 'main_weapon'], 1, 4, 'percent', 30, 100, '通用攻速词条，用于高频输出蓝装。'),
            $this->affix('blue_cdr_01', '灵息', 'CDR', ['necklace', 'bracelet', 'talisman'], 1, 4, 'percent', 30, 110, '通用冷却缩减词条，适合作为法系与功能向饰品词条。'),
            $this->affix('blue_pdef_01', '铁躯', 'PDEF', ['armor', 'belt', 'helm', 'bracelet'], 2, 6, 'percent', 30, 120, '通用物理防御词条。'),
            $this->affix('blue_mdef_01', '灵壁', 'MDEF', ['armor', 'belt', 'helm', 'bracelet', 'talisman'], 2, 6, 'percent', 30, 130, '通用法术防御词条。'),
            $this->affix('blue_final_damage_01', '穿势', 'FINAL_DAMAGE', ['main_weapon', 'off_weapon', 'ring'], 1, 3, 'percent', 30, 140, '高价值输出词条，通常应由模板侧给较低但明确的权重。'),
            $this->affix('blue_final_reduction_01', '镇岳', 'FINAL_REDUCTION', ['armor', 'belt', 'bracelet', 'talisman'], 1, 3, 'percent', 30, 150, '高价值生存词条，适合作为防御向模板的低权重高价值选择。'),
            $this->affix('blue_qi_01', '蕴气', 'QI', ['talisman', 'necklace', 'bracelet'], 3, 8, 'flat', 30, 160, '通用气属性词条，用于法系与功能位蓝装。'),
            $this->affix('blue_loot_01', '天运', 'LOOT_BONUS_PERCENT', ['necklace', 'bracelet', 'talisman'], 1, 3, 'percent', 30, 170, '功能向掉落加成词条。'),
            $this->affix('blue_guard_hp_01', '镇脉', 'HP', ['armor', 'belt', 'helm'], 80, 180, 'flat', 30, 180, '高生命值词条，适合防御模板在模板侧提高权重。'),
            $this->affix('blue_berserk_atk_01', '狂铸', 'ATK', ['main_weapon', 'gloves'], 10, 22, 'flat', 30, 190, '高攻击词条，适合近战输出模板在模板侧提高权重。'),
            $this->affix('blue_hunt_crit_01', '猎芒', 'CRIT_PERCENT', ['main_weapon', 'ring', 'necklace'], 2, 5, 'percent', 30, 200, '高暴击率词条，适合作为暴击流模板的倾向词条。'),
            $this->affix('blue_flame_spell_01', '焚灵', 'SPELL_ATK', ['main_weapon', 'off_weapon', 'talisman'], 3, 7, 'percent', 30, 210, '高术法攻击词条，可由法系模板提高权重。'),
            $this->affix('blue_frost_cdr_01', '玄息', 'CDR', ['bracelet', 'necklace', 'talisman'], 2, 5, 'percent', 30, 220, '高冷却缩减词条，适合作为控制/技能流模板的倾向词条。'),
            $this->affix('blue_evade_dodge_01', '逐风', 'DODGE', ['shoes', 'belt', 'bracelet'], 2, 5, 'percent', 30, 230, '高闪避词条，适合作为闪避流模板的倾向词条。'),
            $this->affix('blue_all_round_01', '灵固', 'DEF', ['armor', 'belt', 'shoes', 'helm'], 4, 10, 'flat', 30, 240, '泛用防御词条，适合作为蓝装默认兜底池。'),
        ];

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
