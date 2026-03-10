<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Legacy/base materials.
            $this->material('桂枝', '桂枝', 'white', 'craft', 10),
            $this->material('玉屑', '玉屑', 'white', 'craft', 20),
            $this->material('白玉碎', '白玉碎', 'blue', 'craft', 30),
            $this->material('妖核', '妖核', 'gold', 'boss', 40),
            $this->material('宗门令', '宗门令', 'white', 'currency', 50),
            $this->material('打孔石', '打孔石', 'blue', 'gem', 60),
            $this->material('玄铁屑', '玄铁屑', 'white', 'craft', 61),
            $this->material('灵髓', '灵髓', 'blue', 'craft', 62),
            $this->material('南山玉印', '南山玉印', 'gold', 'boss', 63),

            // Forge base materials.
            $this->material('forge_base_t1', '初阶打造精华', 'white', 'craft', 100),
            $this->material('forge_base_t2', '中阶打造精华', 'blue', 'craft', 101),
            $this->material('forge_base_t3', '高阶打造精华', 'purple', 'craft', 102),
            $this->material('forge_base_t4', '极阶打造精华', 'gold', 'craft', 103),

            // Forge part materials.
            $this->material('forge_weapon_t1', '初阶兵铸片', 'white', 'craft', 110),
            $this->material('forge_armor_t1', '初阶甲铸片', 'white', 'craft', 111),
            $this->material('forge_cloak_t1', '初阶披饰片', 'white', 'craft', 112),
            $this->material('forge_accessory_t1', '初阶饰铸片', 'white', 'craft', 113),
            $this->material('forge_weapon_t2', '中阶兵铸片', 'blue', 'craft', 114),
            $this->material('forge_armor_t2', '中阶甲铸片', 'blue', 'craft', 115),
            $this->material('forge_cloak_t2', '中阶披饰片', 'blue', 'craft', 116),
            $this->material('forge_accessory_t2', '中阶饰铸片', 'blue', 'craft', 117),
            $this->material('forge_weapon_t3', '高阶兵铸片', 'purple', 'craft', 118),
            $this->material('forge_armor_t3', '高阶甲铸片', 'purple', 'craft', 119),
            $this->material('forge_cloak_t3', '高阶披饰片', 'purple', 'craft', 120),
            $this->material('forge_accessory_t3', '高阶饰铸片', 'purple', 'craft', 121),
            $this->material('forge_weapon_t4', '极阶兵铸片', 'gold', 'craft', 122),
            $this->material('forge_armor_t4', '极阶甲铸片', 'gold', 'craft', 123),
            $this->material('forge_cloak_t4', '极阶披饰片', 'gold', 'craft', 124),
            $this->material('forge_accessory_t4', '极阶饰铸片', 'gold', 'craft', 125),

            // Theme rare materials.
            $this->material('theme_rare_nanshan', '南山灵铁', 'blue', 'craft', 130),
            $this->material('theme_rare_qingqiu', '青丘灵丝', 'purple', 'craft', 131),
            $this->material('theme_rare_kunlun', '昆仑玄晶', 'gold', 'craft', 132),

            // Boss resources.
            $this->material('boss_mark_nanshan', '南山印记', 'blue', 'boss', 140),
            $this->material('boss_core_nanshan', '南山核心', 'purple', 'boss', 141),
            $this->material('boss_mark_qingqiu', '青丘印记', 'purple', 'boss', 142),
            $this->material('boss_core_qingqiu', '青丘核心', 'gold', 'boss', 143),
            $this->material('boss_mark_kunlun', '昆仑印记', 'gold', 'boss', 144),
            $this->material('boss_core_kunlun', '昆仑核心', 'orange', 'boss', 145),

            // Blueprint fragments.
            $this->blueprintFragment('bp_fragment_nanshan', '南山图纸碎片', 'blue', 150),
            $this->blueprintFragment('bp_fragment_qingqiu', '青丘图纸碎片', 'purple', 151),
            $this->blueprintFragment('bp_fragment_kunlun', '昆仑图纸碎片', 'gold', 152),

            // Blueprints.
            $this->blueprint('bp_weapon_nanshan_t1_01', '南山主武图纸·壹', 'blue', 160),
            $this->blueprint('bp_bracelet_qingqiu_t2_01', '青丘手镯图纸·壹', 'purple', 161),
            $this->blueprint('bp_cloak_qingqiu_t2_01', '青丘披饰图纸·壹', 'purple', 162),
            $this->blueprint('bp_ring_nanshan_t2_01', '南山戒指图纸·壹', 'blue', 163),
            $this->blueprint('bp_main_weapon_kunlun_t3_01', '昆仑主武图纸·壹', 'gold', 164),

            // Star materials.
            $this->material('star_stone_t1_common', '初阶星石', 'white', 'star', 170),
            $this->material('star_stone_t2_common', '中阶星石', 'blue', 'star', 171),
            $this->material('star_stone_t3_common', '高阶星石', 'purple', 'star', 172),
            $this->material('star_stone_t4_common', '极阶星石', 'gold', 'star', 173),

            // Refine / wash materials.
            $this->material('refine_sand_basic', '洗炼砂', 'blue', 'refine', 180),
            $this->material('refine_core_advanced', '洗炼精核', 'purple', 'refine', 181),

            // Gems.
            $this->gem('赤晶石', '赤晶石', 'blue', 'attr', ['stat' => 'ATK', 'val' => 1], [1, 2], 70),
            $this->gem('沧澜石', '沧澜石', 'blue', 'attr', ['stat' => 'DEF', 'val' => 1], [1, 2], 80),
            $this->gem('青木石', '青木石', 'blue', 'attr', ['stat' => 'HP', 'val' => 2], [1, 2], 90),
            $this->gem('妖王核心', '妖王核心', 'gold', 'skill', ['skill_id' => 'BING_01', 'modifier' => 'damage_coef', 'val' => 0.08], [3, 4], 100),
            $this->gem('裂石符玉', '裂石符玉', 'purple', 'skill', ['skill_id' => 'BING_01', 'modifier' => 'range', 'val' => 0.12], [3, 4], 101),
        ];

        foreach ($rows as $row) {
            Item::query()->updateOrCreate(
                ['id' => $row['id']],
                $row,
            );
        }
    }

    private function material(string $id, string $name, string $rarity, string $materialType, int $sort): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => 'material',
            'sub_type' => 'material',
            'material_type' => $materialType,
            'rarity' => $rarity,
            'icon' => 'res://assets/icons/icon_ld_01.png',
            'trait' => null,
            'desc' => null,
            'gem_effect' => null,
            'effect_type' => null,
            'target_scope' => null,
            'effect_payload' => null,
            'drop_unlock_level' => 1,
            'socket_limit' => null,
            'source_tags' => [],
            'use_tags' => [$materialType],
            'stack_limit' => 9999,
            'can_compose' => false,
            'can_reforge' => false,
            'is_enabled' => true,
            'sort_order' => $sort,
        ];
    }

    private function blueprint(string $id, string $name, string $rarity, int $sort): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => 'blueprint',
            'sub_type' => 'equipment_blueprint',
            'material_type' => 'blueprint',
            'rarity' => $rarity,
            'icon' => 'res://assets/icons/icon_key_02.png',
            'trait' => null,
            'desc' => '用于高品质升品打造',
            'gem_effect' => null,
            'effect_type' => null,
            'target_scope' => null,
            'effect_payload' => null,
            'drop_unlock_level' => 20,
            'socket_limit' => null,
            'source_tags' => ['boss_drop', 'compose'],
            'use_tags' => ['high_forge'],
            'stack_limit' => 999,
            'can_compose' => false,
            'can_reforge' => false,
            'is_enabled' => true,
            'sort_order' => $sort,
        ];
    }

    private function blueprintFragment(string $id, string $name, string $rarity, int $sort): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => 'blueprint_fragment',
            'sub_type' => 'theme_blueprint_fragment',
            'material_type' => 'blueprint',
            'rarity' => $rarity,
            'icon' => 'res://assets/icons/icon_key_01.png',
            'trait' => null,
            'desc' => '20个可合成主题图纸',
            'gem_effect' => null,
            'effect_type' => null,
            'target_scope' => null,
            'effect_payload' => null,
            'drop_unlock_level' => 20,
            'socket_limit' => null,
            'source_tags' => ['boss_drop'],
            'use_tags' => ['blueprint_compose'],
            'stack_limit' => 9999,
            'can_compose' => true,
            'can_reforge' => false,
            'is_enabled' => true,
            'sort_order' => $sort,
        ];
    }

    private function gem(string $id, string $name, string $rarity, string $gemType, array $payload, array $socketLimit, int $sort): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => 'gem',
            'sub_type' => $gemType,
            'material_type' => 'gem',
            'rarity' => $rarity,
            'icon' => 'res://assets/icons/icon_ld_02.png',
            'trait' => null,
            'desc' => $gemType === 'skill' ? '技能强化宝石' : '属性强化宝石',
            'gem_effect' => $gemType === 'attr' ? $payload : null,
            'effect_type' => $gemType === 'skill' ? 'skill_modifier' : 'stat',
            'target_scope' => $gemType === 'skill' ? (string) ($payload['skill_id'] ?? 'global') : 'global',
            'effect_payload' => $payload,
            'drop_unlock_level' => $gemType === 'skill' ? 40 : 1,
            'socket_limit' => $socketLimit,
            'source_tags' => ['boss_drop', 'dungeon_drop'],
            'use_tags' => ['socket'],
            'stack_limit' => 999,
            'can_compose' => true,
            'can_reforge' => in_array($rarity, ['purple', 'gold', 'orange'], true),
            'is_enabled' => true,
            'sort_order' => $sort,
        ];
    }
}
