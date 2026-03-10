<?php

namespace Database\Seeders;

use App\Models\BlueGearTemplate;
use Illuminate\Database\Seeder;

class BlueGearTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'template_id' => 'blue_main_weapon_nanshan_01',
                'name' => '南山试炼主武',
                'blue_pool_id' => 'pool_nanshan_t1',
                'slot_id' => 'main_weapon',
                'flow_tag' => 'berserk',
                'required_level' => 20,
                'white_stats' => ['ATK' => 18, 'HP' => 22],
                'affix_count' => 1,
                'affix_pool_tags' => ['atk', 'crit'],
                'icon' => '',
                'sort_order' => 10,
                'is_enabled' => true,
            ],
            [
                'template_id' => 'blue_armor_nanshan_01',
                'name' => '南山试炼盔甲',
                'blue_pool_id' => 'pool_nanshan_t1',
                'slot_id' => 'armor',
                'flow_tag' => 'guard',
                'required_level' => 20,
                'white_stats' => ['DEF' => 11, 'HP' => 52],
                'affix_count' => 1,
                'affix_pool_tags' => ['def', 'hp'],
                'icon' => '',
                'sort_order' => 20,
                'is_enabled' => true,
            ],
            [
                'template_id' => 'blue_necklace_qingqiu_01',
                'name' => '青丘试炼项链',
                'blue_pool_id' => 'pool_qingqiu_t2',
                'slot_id' => 'necklace',
                'flow_tag' => 'frost',
                'required_level' => 40,
                'white_stats' => ['HP' => 66, 'ATK' => 6],
                'affix_count' => 2,
                'affix_pool_tags' => ['hp', 'loot'],
                'icon' => '',
                'sort_order' => 30,
                'is_enabled' => true,
            ],
        ];

        foreach ($rows as $row) {
            BlueGearTemplate::query()->updateOrCreate(
                ['template_id' => $row['template_id']],
                $row,
            );
        }
    }
}
