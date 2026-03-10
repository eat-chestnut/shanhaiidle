<?php

namespace Database\Seeders;

use App\Models\BlueAffix;
use Illuminate\Database\Seeder;

class BlueAffixPoolSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['affix_id' => 'blue_atk_flat_1', 'affix_name' => '锐击', 'stat' => 'ATK', 'slot_tags' => ['main_weapon', 'off_weapon', 'gloves', 'ring'], 'flow_tags' => ['berserk', 'hunt'], 'min_value' => 2, 'max_value' => 5, 'value_mode' => 'flat', 'weight' => 40, 'unlock_level' => 30, 'sort_order' => 10],
            ['affix_id' => 'blue_def_flat_1', 'affix_name' => '坚壁', 'stat' => 'DEF', 'slot_tags' => ['armor', 'belt', 'shoes', 'helm', 'bracelet'], 'flow_tags' => ['guard', 'frost'], 'min_value' => 2, 'max_value' => 5, 'value_mode' => 'flat', 'weight' => 40, 'unlock_level' => 30, 'sort_order' => 20],
            ['affix_id' => 'blue_hp_flat_1', 'affix_name' => '生息', 'stat' => 'HP', 'slot_tags' => ['armor', 'belt', 'shoes', 'helm', 'necklace', 'talisman'], 'flow_tags' => ['all'], 'min_value' => 8, 'max_value' => 18, 'value_mode' => 'flat', 'weight' => 35, 'unlock_level' => 30, 'sort_order' => 30],
            ['affix_id' => 'blue_crit_pct_1', 'affix_name' => '破势', 'stat' => 'CRIT_PERCENT', 'slot_tags' => ['main_weapon', 'off_weapon', 'ring', 'necklace'], 'flow_tags' => ['berserk', 'shadow'], 'min_value' => 1, 'max_value' => 3, 'value_mode' => 'percent', 'weight' => 20, 'unlock_level' => 30, 'sort_order' => 40],
        ];

        foreach ($rows as $row) {
            $row['is_enabled'] = true;
            BlueAffix::query()->updateOrCreate(
                ['affix_id' => $row['affix_id']],
                $row,
            );
        }
    }
}
