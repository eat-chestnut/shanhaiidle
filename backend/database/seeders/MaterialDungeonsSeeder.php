<?php

namespace Database\Seeders;

use App\Models\MaterialDungeon;
use Illuminate\Database\Seeder;

class MaterialDungeonsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['dungeon_id' => 'dun_craft_20', 'name' => '打造材料副本', 'dungeon_type' => 'craft', 'unlock_level' => 20, 'layer_config' => ['max_layer' => 20], 'drop_pools' => ['forge_base_t1', 'forge_weapon_t1', 'forge_armor_t1'], 'stamina_cost' => 10, 'daily_limit' => 8, 'description' => '产出打造基础材料', 'sort_order' => 10, 'is_enabled' => true],
            ['dungeon_id' => 'dun_star_20', 'name' => '星材副本', 'dungeon_type' => 'star', 'unlock_level' => 20, 'layer_config' => ['max_layer' => 20], 'drop_pools' => ['star_stone_t1_common', 'star_stone_t2_common'], 'stamina_cost' => 10, 'daily_limit' => 8, 'description' => '产出升星材料', 'sort_order' => 20, 'is_enabled' => true],
            ['dungeon_id' => 'dun_blueprint_30', 'name' => '图纸副本', 'dungeon_type' => 'blueprint', 'unlock_level' => 30, 'layer_config' => ['max_layer' => 20], 'drop_pools' => ['bp_fragment_nanshan', 'bp_fragment_qingqiu'], 'stamina_cost' => 12, 'daily_limit' => 6, 'description' => '产出图纸碎片', 'sort_order' => 30, 'is_enabled' => true],
            ['dungeon_id' => 'dun_boss_40', 'name' => 'Boss材料副本', 'dungeon_type' => 'boss', 'unlock_level' => 40, 'layer_config' => ['max_layer' => 20], 'drop_pools' => ['boss_mark_nanshan', 'boss_core_nanshan'], 'stamina_cost' => 15, 'daily_limit' => 5, 'description' => '产出Boss印记与核心', 'sort_order' => 40, 'is_enabled' => true],
            ['dungeon_id' => 'dun_gem_40', 'name' => '宝石副本', 'dungeon_type' => 'gem', 'unlock_level' => 40, 'layer_config' => ['max_layer' => 20], 'drop_pools' => ['赤晶石', '沧澜石', '青木石', '裂石符玉'], 'stamina_cost' => 12, 'daily_limit' => 6, 'description' => '产出属性/技能宝石', 'sort_order' => 50, 'is_enabled' => true],
            ['dungeon_id' => 'dun_refine_50', 'name' => '洗练材料副本', 'dungeon_type' => 'refine', 'unlock_level' => 50, 'layer_config' => ['max_layer' => 20], 'drop_pools' => ['refine_sand_basic', 'refine_core_advanced'], 'stamina_cost' => 15, 'daily_limit' => 5, 'description' => '产出洗练材料', 'sort_order' => 60, 'is_enabled' => true],
        ];

        foreach ($rows as $row) {
            MaterialDungeon::query()->updateOrCreate(
                ['dungeon_id' => $row['dungeon_id']],
                $row,
            );
        }
    }
}
