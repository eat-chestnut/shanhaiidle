<?php

namespace Database\Seeders;

use App\Models\PurpleAffix;
use Illuminate\Database\Seeder;

class PurpleAffixPoolSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->affix('purple_atk_flat_01', '战意', 'ATK', ['main_weapon', 'off_weapon', 'gloves', 'ring', 'necklace'], ['berserk', 'hunt', 'flame'], 'purple', 16, 36, 'flat', 100, 50, 10),
            $this->affix('purple_hp_flat_01', '长生', 'HP', ['armor', 'belt', 'shoes', 'helm', 'bracelet', 'talisman'], ['berserk', 'guard', 'hunt', 'evade', 'flame', 'frost'], 'purple', 120, 280, 'flat', 100, 50, 20),
            $this->affix('purple_def_flat_01', '山岳', 'DEF', ['armor', 'belt', 'shoes', 'helm', 'bracelet'], ['guard', 'frost', 'evade'], 'purple', 14, 32, 'flat', 96, 50, 30),
            $this->affix('purple_crit_pct_01', '破军', 'CRIT_PERCENT', ['main_weapon', 'ring', 'necklace', 'gloves'], ['berserk', 'hunt'], 'purple', 3, 7, 'percent', 82, 50, 40),
            $this->affix('purple_crit_dmg_01', '绝锋', 'CRIT_DMG', ['main_weapon', 'ring', 'bracelet', 'necklace'], ['hunt', 'flame'], 'purple', 6, 14, 'percent', 78, 50, 50),
            $this->affix('purple_melee_atk_01', '斩岳', 'MELEE_ATK', ['main_weapon', 'off_weapon', 'gloves', 'ring'], ['berserk', 'guard'], 'purple', 4, 10, 'percent', 72, 50, 60),
            $this->affix('purple_ranged_atk_01', '穿云', 'RANGED_ATK', ['main_weapon', 'off_weapon', 'ring', 'necklace'], ['hunt', 'evade'], 'purple', 4, 10, 'percent', 72, 50, 70),
            $this->affix('purple_spell_atk_01', '焚脉', 'SPELL_ATK', ['main_weapon', 'off_weapon', 'ring', 'necklace', 'talisman'], ['flame', 'frost'], 'purple', 4, 10, 'percent', 74, 50, 80),
            $this->affix('purple_final_damage_01', '绝厄', 'FINAL_DAMAGE', ['main_weapon', 'off_weapon', 'ring', 'necklace'], ['berserk', 'flame', 'hunt'], 'purple', 2, 6, 'percent', 54, 50, 90),
            $this->affix('purple_cdr_01', '归息', 'CDR', ['necklace', 'bracelet', 'talisman'], ['frost', 'flame', 'evade'], 'purple', 2, 6, 'percent', 60, 50, 100),
            $this->affix('purple_dodge_01', '流影', 'DODGE', ['shoes', 'belt', 'bracelet'], ['evade', 'hunt'], 'purple', 2, 6, 'percent', 58, 50, 110),
            $this->affix('purple_pdef_01', '镇甲', 'PDEF', ['armor', 'belt', 'helm', 'bracelet'], ['guard'], 'purple', 4, 10, 'percent', 52, 50, 120),
            $this->affix('purple_mdef_01', '玄屏', 'MDEF', ['armor', 'belt', 'helm', 'bracelet', 'talisman'], ['frost', 'guard'], 'purple', 4, 10, 'percent', 50, 50, 130),
            $this->affix('purple_final_reduction_01', '封脉', 'FINAL_REDUCTION', ['armor', 'belt', 'bracelet', 'talisman'], ['guard', 'frost'], 'purple', 2, 6, 'percent', 48, 50, 140),
            $this->affix('purple_qi_01', '藏神', 'QI', ['talisman', 'necklace', 'bracelet'], ['frost', 'flame'], 'purple', 6, 14, 'flat', 46, 50, 150),
            $this->affix('purple_loot_01', '寻珍', 'LOOT_BONUS_PERCENT', ['necklace', 'bracelet', 'talisman'], ['evade', 'frost'], 'purple', 2, 5, 'percent', 28, 50, 160),
            $this->affix('gold_atk_flat_01', '焚世', 'ATK', ['main_weapon', 'off_weapon', 'gloves', 'ring'], ['berserk', 'flame'], 'gold', 28, 56, 'flat', 32, 60, 170),
            $this->affix('gold_hp_flat_01', '永镇', 'HP', ['armor', 'belt', 'helm', 'bracelet', 'talisman'], ['guard', 'frost'], 'gold', 220, 420, 'flat', 32, 60, 180),
            $this->affix('gold_crit_pct_01', '天猎', 'CRIT_PERCENT', ['main_weapon', 'ring', 'necklace'], ['hunt'], 'gold', 4, 8, 'percent', 28, 60, 190),
            $this->affix('gold_spell_atk_01', '玄炎', 'SPELL_ATK', ['main_weapon', 'off_weapon', 'ring', 'talisman'], ['flame', 'frost'], 'gold', 5, 12, 'percent', 28, 60, 200),
            $this->affix('gold_final_damage_01', '山海断厄', 'FINAL_DAMAGE', ['main_weapon', 'off_weapon', 'ring'], ['berserk', 'hunt', 'flame'], 'gold', 3, 7, 'percent', 24, 60, 210),
            $this->affix('gold_cdr_01', '空照', 'CDR', ['bracelet', 'necklace', 'talisman'], ['frost', 'evade'], 'gold', 3, 7, 'percent', 24, 60, 220),
            $this->affix('gold_dodge_01', '九影', 'DODGE', ['shoes', 'bracelet', 'belt'], ['evade'], 'gold', 3, 7, 'percent', 22, 60, 230),
            $this->affix('gold_final_reduction_01', '封岳', 'FINAL_REDUCTION', ['armor', 'belt', 'bracelet', 'talisman'], ['guard'], 'gold', 3, 7, 'percent', 22, 60, 240),
        ];

        $validIds = [];

        foreach ($rows as $row) {
            $validIds[] = $row['affix_id'];
            PurpleAffix::query()->updateOrCreate(
                ['affix_id' => $row['affix_id']],
                $row,
            );
        }

        PurpleAffix::query()->whereNotIn('affix_id', $validIds)->delete();
    }

    private function affix(
        string $affixId,
        string $affixName,
        string $stat,
        array $slotTags,
        array $flowTags,
        string $rarityTier,
        int $minValue,
        int $maxValue,
        string $valueMode,
        int $weight,
        int $unlockLevel,
        int $sortOrder,
    ): array {
        return [
            'affix_id' => $affixId,
            'affix_name' => $affixName,
            'stat' => $stat,
            'slot_tags' => $slotTags,
            'flow_tags' => $flowTags,
            'rarity_tier' => $rarityTier,
            'min_value' => $minValue,
            'max_value' => $maxValue,
            'value_mode' => $valueMode,
            'weight' => $weight,
            'unlock_level' => $unlockLevel,
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }
}
