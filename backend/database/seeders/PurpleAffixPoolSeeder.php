<?php

namespace Database\Seeders;

use App\Models\PurpleAffix;
use Illuminate\Database\Seeder;

class PurpleAffixPoolSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['affix_id' => 'purple_atk_flat_1', 'affix_name' => '战意', 'stat' => 'ATK', 'slot_tags' => ['main_weapon', 'off_weapon', 'ring'], 'flow_tags' => ['berserk', 'hunt'], 'rarity_tier' => 'purple', 'min_value' => 4, 'max_value' => 9, 'value_mode' => 'flat', 'weight' => 30, 'unlock_level' => 50, 'sort_order' => 10],
            ['affix_id' => 'purple_def_flat_1', 'affix_name' => '山岳', 'stat' => 'DEF', 'slot_tags' => ['armor', 'belt', 'helm', 'bracelet'], 'flow_tags' => ['guard', 'frost'], 'rarity_tier' => 'purple', 'min_value' => 4, 'max_value' => 9, 'value_mode' => 'flat', 'weight' => 30, 'unlock_level' => 50, 'sort_order' => 20],
            ['affix_id' => 'purple_hp_flat_1', 'affix_name' => '长生', 'stat' => 'HP', 'slot_tags' => ['armor', 'belt', 'shoes', 'helm', 'talisman'], 'flow_tags' => ['all'], 'rarity_tier' => 'purple', 'min_value' => 16, 'max_value' => 32, 'value_mode' => 'flat', 'weight' => 26, 'unlock_level' => 50, 'sort_order' => 30],
            ['affix_id' => 'gold_loot_pct_1', 'affix_name' => '天运', 'stat' => 'LOOT_BONUS_PERCENT', 'slot_tags' => ['necklace', 'ring', 'bracelet'], 'flow_tags' => ['all'], 'rarity_tier' => 'gold', 'min_value' => 2, 'max_value' => 5, 'value_mode' => 'percent', 'weight' => 14, 'unlock_level' => 60, 'sort_order' => 40],
        ];

        foreach ($rows as $row) {
            $row['is_enabled'] = true;
            PurpleAffix::query()->updateOrCreate(
                ['affix_id' => $row['affix_id']],
                $row,
            );
        }
    }
}
