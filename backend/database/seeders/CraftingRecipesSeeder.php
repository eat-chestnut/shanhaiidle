<?php

namespace Database\Seeders;

use App\Models\CraftingRecipe;
use Illuminate\Database\Seeder;

class CraftingRecipesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'recipe_id' => 'rcp_craft_main_weapon_t1',
                'recipe_type' => 'craft',
                'output_type' => 'equip',
                'output_id' => 'main_weapon_nanshan_t1_normal_01',
                'output_count' => 1,
                'unlock_level' => 20,
                'cost_items' => [
                    ['item_id' => 'forge_base_t1', 'count' => 12],
                    ['item_id' => 'forge_weapon_t1', 'count' => 6],
                ],
                'cost_gold' => 300,
                'cost_currency' => null,
                'notes' => '普通打造示例',
                'sort_order' => 10,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_compose_bp_qingqiu',
                'recipe_type' => 'compose',
                'output_type' => 'material',
                'output_id' => 'bp_bracelet_qingqiu_t2_01',
                'output_count' => 1,
                'unlock_level' => 30,
                'cost_items' => [
                    ['item_id' => 'bp_fragment_qingqiu', 'count' => 20],
                ],
                'cost_gold' => 500,
                'cost_currency' => null,
                'notes' => '20碎片合成图纸',
                'sort_order' => 20,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_exchange_talisman_40',
                'recipe_type' => 'exchange',
                'output_type' => 'equip',
                'output_id' => 'talisman_shop_40_01',
                'output_count' => 1,
                'unlock_level' => 40,
                'cost_items' => [
                    ['item_id' => '宗门令', 'count' => 20],
                ],
                'cost_gold' => 1000,
                'cost_currency' => null,
                'notes' => '护身符商店兑换',
                'sort_order' => 30,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_gem_compose_blue_attr',
                'recipe_type' => 'compose',
                'output_type' => 'gem',
                'output_id' => '裂石符玉',
                'output_count' => 1,
                'unlock_level' => 40,
                'cost_items' => [
                    ['item_id' => '赤晶石', 'count' => 1],
                    ['item_id' => '沧澜石', 'count' => 1],
                    ['item_id' => '青木石', 'count' => 1],
                ],
                'cost_gold' => 600,
                'cost_currency' => null,
                'notes' => '宝石合成示例（同大类同阶）',
                'sort_order' => 40,
                'is_enabled' => true,
            ],
        ];

        foreach ($rows as $row) {
            CraftingRecipe::query()->updateOrCreate(
                ['recipe_id' => $row['recipe_id']],
                $row,
            );
        }
    }
}
