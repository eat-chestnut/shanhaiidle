<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            [
                'id' => '桂枝',
                'name' => '桂枝',
                'type' => 'item',
                'rarity' => 'white',
                'icon' => 'res://assets/icons/icon_ld_01.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 10,
            ],
            [
                'id' => '玉屑',
                'name' => '玉屑',
                'type' => 'item',
                'rarity' => 'white',
                'icon' => 'res://assets/icons/icon_ld_02.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 20,
            ],
            [
                'id' => '白玉碎',
                'name' => '白玉碎',
                'type' => 'item',
                'rarity' => 'blue',
                'icon' => 'res://assets/icons/icon_ld_03.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 30,
            ],
            [
                'id' => '妖核',
                'name' => '妖核',
                'type' => 'item',
                'rarity' => 'gold',
                'icon' => 'res://assets/icons/icon_killskill.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 40,
            ],
            [
                'id' => '宗门令',
                'name' => '宗门令',
                'type' => 'item',
                'rarity' => 'white',
                'icon' => 'res://assets/icons/icon_key_01.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 50,
            ],
            [
                'id' => '打孔石',
                'name' => '打孔石',
                'type' => 'item',
                'rarity' => 'blue',
                'icon' => 'res://assets/icons/icon_key_02.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 60,
            ],
            [
                'id' => '玄铁屑',
                'name' => '玄铁屑',
                'type' => 'item',
                'rarity' => 'white',
                'icon' => 'res://assets/icons/icon_ld_01.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 61,
            ],
            [
                'id' => '灵髓',
                'name' => '灵髓',
                'type' => 'item',
                'rarity' => 'blue',
                'icon' => 'res://assets/icons/icon_ld_02.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 62,
            ],
            [
                'id' => '南山玉印',
                'name' => '南山玉印',
                'type' => 'item',
                'rarity' => 'gold',
                'icon' => 'res://assets/icons/icon_killskill.png',
                'trait' => null,
                'gem_effect' => null,
                'sort_order' => 63,
            ],
            [
                'id' => '赤晶石',
                'name' => '赤晶石',
                'type' => 'gem',
                'rarity' => 'blue',
                'icon' => 'res://assets/icons/icon_ld_01.png',
                'trait' => null,
                'gem_effect' => ['stat' => 'ATK', 'val' => 1],
                'sort_order' => 70,
            ],
            [
                'id' => '沧澜石',
                'name' => '沧澜石',
                'type' => 'gem',
                'rarity' => 'blue',
                'icon' => 'res://assets/icons/icon_ld_02.png',
                'trait' => null,
                'gem_effect' => ['stat' => 'DEF', 'val' => 1],
                'sort_order' => 80,
            ],
            [
                'id' => '青木石',
                'name' => '青木石',
                'type' => 'gem',
                'rarity' => 'blue',
                'icon' => 'res://assets/icons/icon_ld_03.png',
                'trait' => null,
                'gem_effect' => ['stat' => 'HP', 'val' => 2],
                'sort_order' => 90,
            ],
            [
                'id' => '妖王核心',
                'name' => '妖王核心',
                'type' => 'gem',
                'rarity' => 'gold',
                'icon' => 'res://assets/icons/icon_killskill.png',
                'trait' => '妖王之力：战斗中可灌注武器（未启用），后续可解锁特殊效果。',
                'gem_effect' => ['stat' => 'LOOT_BONUS_PERCENT', 'val' => 3],
                'sort_order' => 100,
            ],
        ];

        foreach ($rows as $index => $row) {
            $row['is_enabled'] = true;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $rows[$index] = $row;
        }

        foreach ($rows as $row) {
            Item::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'rarity' => $row['rarity'],
                    'icon' => $row['icon'],
                    'trait' => $row['trait'],
                    'gem_effect' => $row['gem_effect'],
                    'is_enabled' => $row['is_enabled'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
