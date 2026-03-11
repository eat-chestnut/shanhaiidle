<?php

namespace Database\Seeders;

use App\Models\MaterialDungeon;
use Illuminate\Database\Seeder;

class MaterialDungeonsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'dungeon_id' => 'dungeon_craft',
                'name' => '堂庭采玉台',
                'dungeon_type' => 'craft',
                'unlock_level' => 20,
                'layer_config' => [
                    ['layer' => 1, 'unlock_level' => 20, 'recommended_power' => 2200, 'drop_pool' => ['桂木', '棪木', '基础打造材料']],
                    ['layer' => 2, 'unlock_level' => 30, 'recommended_power' => 5200, 'drop_pool' => ['白玉髓', '水玉晶', '基础打造材料']],
                    ['layer' => 3, 'unlock_level' => 40, 'recommended_power' => 9800, 'drop_pool' => ['赤金砂', '白金砂', '鹿蜀纹角碎片']],
                    ['layer' => 4, 'unlock_level' => 60, 'recommended_power' => 18200, 'drop_pool' => ['青雘石', '箕尾镇脉石碎片', '终章材料']],
                ],
                'drop_pools' => ['桂木', '棪木', '白玉髓', '水玉晶', '赤金砂', '白金砂', '青雘石'],
                'stamina_cost' => 10,
                'daily_limit' => 6,
                'description' => '主要产出基础打造材料与矿石材料，是1—60级锻造的底层资源来源。',
                'sort_order' => 10,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'dungeon_star',
                'name' => '招摇祝余圃',
                'dungeon_type' => 'star',
                'unlock_level' => 20,
                'layer_config' => [
                    ['layer' => 1, 'unlock_level' => 20, 'recommended_power' => 2400, 'drop_pool' => ['star_stone_t1_common']],
                    ['layer' => 2, 'unlock_level' => 40, 'recommended_power' => 9200, 'drop_pool' => ['star_stone_t1_common', 'star_stone_t2_common']],
                    ['layer' => 3, 'unlock_level' => 50, 'recommended_power' => 14800, 'drop_pool' => ['star_stone_t2_common', 'star_stone_t3_common']],
                    ['layer' => 4, 'unlock_level' => 60, 'recommended_power' => 21000, 'drop_pool' => ['star_stone_t3_common', 'star_stone_t4_common']],
                ],
                'drop_pools' => ['star_stone_t1_common', 'star_stone_t2_common', 'star_stone_t3_common', 'star_stone_t4_common'],
                'stamina_cost' => 10,
                'daily_limit' => 6,
                'description' => '产出升星材料，对应1—3、4—6、7—8、9—10星的完整成长线。',
                'sort_order' => 20,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'dungeon_blueprint',
                'name' => '亶爰秘禁',
                'dungeon_type' => 'blueprint',
                'unlock_level' => 30,
                'layer_config' => [
                    ['layer' => 1, 'unlock_level' => 30, 'recommended_power' => 6200, 'drop_pool' => ['bp_fragment_nanshan', '白猿王图纸碎片', '猨翼图纸碎片']],
                    ['layer' => 2, 'unlock_level' => 40, 'recommended_power' => 9800, 'drop_pool' => ['bp_set_lushu_40', 'bp_set_xuangui_40', 'bp_set_migu_40']],
                    ['layer' => 3, 'unlock_level' => 50, 'recommended_power' => 15000, 'drop_pool' => ['bp_set_chiyu_40', 'bp_set_qingqiu_40', 'bp_fragment_qingqiu']],
                    ['layer' => 4, 'unlock_level' => 60, 'recommended_power' => 22000, 'drop_pool' => ['bp_set_lushu_60', 'bp_set_qingqiu_60', '60级主武器图纸']],
                ],
                'drop_pools' => ['bp_fragment_nanshan', 'bp_fragment_qingqiu', '白猿王图纸碎片', '猨翼图纸碎片', 'bp_set_lushu_40', 'bp_set_qingqiu_60', '60级主武器图纸'],
                'stamina_cost' => 12,
                'daily_limit' => 5,
                'description' => '产出各阶段图纸碎片与部分完整图纸，用于打造与升阶前置。',
                'sort_order' => 30,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'dungeon_boss_mat',
                'name' => '杻阳守脉战',
                'dungeon_type' => 'boss',
                'unlock_level' => 40,
                'layer_config' => [
                    ['layer' => 1, 'unlock_level' => 40, 'recommended_power' => 10800, 'drop_pool' => ['boss_mark_nanshan', '招摇印记', '堂庭印记', '猨翼印记']],
                    ['layer' => 2, 'unlock_level' => 45, 'recommended_power' => 13200, 'drop_pool' => ['杻阳印记', '旋龟印', '旋龟核心']],
                    ['layer' => 3, 'unlock_level' => 50, 'recommended_power' => 16800, 'drop_pool' => ['柢山印记', '鯥王核心', '护身符兑换材料']],
                    ['layer' => 4, 'unlock_level' => 60, 'recommended_power' => 23000, 'drop_pool' => ['青丘印记', '箕尾印记', '青丘核心', '镇脉核心']],
                ],
                'drop_pools' => ['boss_mark_nanshan', '招摇印记', '堂庭印记', '猨翼印记', '杻阳印记', '旋龟印', '旋龟核心', '柢山印记', '鯥王核心', '青丘核心', '镇脉核心'],
                'stamina_cost' => 15,
                'daily_limit' => 4,
                'description' => '产出印记、核心与护身符兑换材料，是40级后高阶配方与终章成长的重要资源来源。',
                'sort_order' => 40,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'dungeon_gem',
                'name' => '基山秘藏',
                'dungeon_type' => 'gem',
                'unlock_level' => 40,
                'layer_config' => [
                    ['layer' => 1, 'unlock_level' => 40, 'recommended_power' => 11200, 'drop_pool' => ['赤晶石', '沧澜石', '青木石', '属性宝石碎片·白蓝']],
                    ['layer' => 2, 'unlock_level' => 45, 'recommended_power' => 13800, 'drop_pool' => ['鹿蜀纹石', '迷榖风晶', '技能宝石碎片']],
                    ['layer' => 3, 'unlock_level' => 50, 'recommended_power' => 17600, 'drop_pool' => ['赤鱬内珠', '旋龟甲石', 'gem_skill_guanguan_seal']],
                    ['layer' => 4, 'unlock_level' => 60, 'recommended_power' => 23800, 'drop_pool' => ['白金髓', '灌灌羽晶', '高阶技能宝石碎片', 'gem_skill_jiwei_seal']],
                ],
                'drop_pools' => ['赤晶石', '沧澜石', '青木石', '鹿蜀纹石', '迷榖风晶', '赤鱬内珠', '旋龟甲石', '白金髓', '灌灌羽晶', '技能宝石碎片', '高阶技能宝石碎片'],
                'stamina_cost' => 12,
                'daily_limit' => 5,
                'description' => '产出属性宝石、技能宝石与对应碎片，40级后正式进入宝石成长线。',
                'sort_order' => 50,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'dungeon_refine',
                'name' => '箕尾洗髓坛',
                'dungeon_type' => 'refine',
                'unlock_level' => 50,
                'layer_config' => [
                    ['layer' => 1, 'unlock_level' => 50, 'recommended_power' => 17000, 'drop_pool' => ['refine_sand_basic', 'equipment_essence']],
                    ['layer' => 2, 'unlock_level' => 55, 'recommended_power' => 19200, 'drop_pool' => ['refine_sand_basic', 'refine_core_advanced', 'equipment_essence']],
                    ['layer' => 3, 'unlock_level' => 60, 'recommended_power' => 24600, 'drop_pool' => ['refine_core_advanced', 'purple_refine_dust', 'equipment_essence']],
                ],
                'drop_pools' => ['refine_sand_basic', 'refine_core_advanced', 'equipment_essence', 'purple_refine_dust'],
                'stamina_cost' => 15,
                'daily_limit' => 4,
                'description' => '50级开放的洗练材料副本，承担紫色洗练与终章 build 微调资源。',
                'sort_order' => 60,
                'is_enabled' => true,
            ],
        ];

        $validIds = [];

        foreach ($rows as $row) {
            $validIds[] = $row['dungeon_id'];
            MaterialDungeon::query()->updateOrCreate(
                ['dungeon_id' => $row['dungeon_id']],
                $row,
            );
        }

        MaterialDungeon::query()->whereNotIn('dungeon_id', $validIds)->delete();
    }
}
