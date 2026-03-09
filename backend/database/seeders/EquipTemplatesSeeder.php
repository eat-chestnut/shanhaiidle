<?php

namespace Database\Seeders;

use App\Models\EquipTemplate;
use Illuminate\Database\Seeder;

class EquipTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'id' => 'eq_weapon_001',
                'name' => '白·兵器',
                'slot' => 'weapon',
                'rarity' => 'white',
                'main_stat' => 'ATK',
                'main_min' => 1,
                'main_max' => 2,
                'unidentified_chance' => 0.35,
                'icon' => '',
                'set_id' => 'set_zhaoyao',
                'effects' => [
                    ['type' => 'stat', 'stat' => 'ATK', 'val' => 1],
                ],
                'is_enabled' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 'eq_weapon_002',
                'name' => '蓝·兵器',
                'slot' => 'weapon',
                'rarity' => 'blue',
                'main_stat' => 'ATK',
                'main_min' => 2,
                'main_max' => 3,
                'unidentified_chance' => 0.20,
                'icon' => '',
                'set_id' => 'set_qingqiu',
                'effects' => [
                    ['type' => 'skill_level', 'skill_id' => 'BING_01', 'val' => 1],
                ],
                'is_enabled' => true,
                'sort_order' => 20,
            ],
            [
                'id' => 'eq_armor_001',
                'name' => '白·护甲',
                'slot' => 'armor',
                'rarity' => 'white',
                'main_stat' => 'HP',
                'main_min' => 2,
                'main_max' => 4,
                'unidentified_chance' => 0.35,
                'icon' => '',
                'set_id' => 'set_zhaoyao',
                'effects' => [
                    ['type' => 'stat', 'stat' => 'HP', 'val' => 2],
                ],
                'is_enabled' => true,
                'sort_order' => 30,
            ],
            [
                'id' => 'eq_armor_002',
                'name' => '蓝·护甲',
                'slot' => 'armor',
                'rarity' => 'blue',
                'main_stat' => 'DEF',
                'main_min' => 1,
                'main_max' => 2,
                'unidentified_chance' => 0.20,
                'icon' => '',
                'set_id' => 'set_zhaoyao',
                'effects' => [
                    ['type' => 'stat', 'stat' => 'DEF', 'val' => 1],
                ],
                'is_enabled' => true,
                'sort_order' => 40,
            ],
            [
                'id' => 'eq_ring_001',
                'name' => '蓝·戒指',
                'slot' => 'ring',
                'rarity' => 'blue',
                'main_stat' => 'LOOT_BONUS_PERCENT',
                'main_min' => 1,
                'main_max' => 2,
                'unidentified_chance' => 0.15,
                'icon' => '',
                'set_id' => 'set_qingqiu',
                'effects' => [
                    ['type' => 'stat', 'stat' => 'LOOT_BONUS_PERCENT', 'val' => 2],
                ],
                'is_enabled' => true,
                'sort_order' => 50,
            ],
        ];

        foreach ($rows as $row) {
            EquipTemplate::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'slot' => $row['slot'],
                    'rarity' => $row['rarity'],
                    'main_stat' => $row['main_stat'],
                    'main_min' => $row['main_min'],
                    'main_max' => $row['main_max'],
                    'unidentified_chance' => $row['unidentified_chance'] ?? 0,
                    'icon' => $row['icon'],
                    'set_id' => $row['set_id'] ?? null,
                    'effects' => $row['effects'],
                    'is_enabled' => $row['is_enabled'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
