<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];
        $sort = 10;

        $add = function (array $row) use (&$rows, &$sort): void {
            $row['icon'] = $row['icon'] ?? '';
            $row['trait'] = $row['trait'] ?? '';
            $row['desc'] = $row['desc'] ?? '';
            $row['gem_effect'] = $row['gem_effect'] ?? null;
            $row['effect_type'] = $row['effect_type'] ?? null;
            $row['target_scope'] = $row['target_scope'] ?? null;
            $row['effect_payload'] = $row['effect_payload'] ?? null;
            $row['drop_unlock_level'] = $row['drop_unlock_level'] ?? 1;
            $row['socket_limit'] = $row['socket_limit'] ?? null;
            $row['source_tags'] = $row['source_tags'] ?? [];
            $row['use_tags'] = $row['use_tags'] ?? [];
            $row['stack_limit'] = $row['stack_limit'] ?? 9999;
            $row['can_compose'] = $row['can_compose'] ?? false;
            $row['can_reforge'] = $row['can_reforge'] ?? false;
            $row['is_enabled'] = $row['is_enabled'] ?? true;
            $row['sort_order'] = $row['sort_order'] ?? $sort;
            $rows[] = $row;
            $sort += 10;
        };

        $currency = fn (string $id, string $name, string $desc, array $useTags = []): array => [
            'id' => $id,
            'name' => $name,
            'type' => 'currency',
            'sub_type' => 'currency',
            'material_type' => 'currency',
            'rarity' => 'gold',
            'desc' => $desc,
            'source_tags' => ['主线', '副本', '礼包'],
            'use_tags' => $useTags,
            'stack_limit' => 999999999,
        ];

        $material = function (
            string $id,
            string $name,
            string $subType,
            string $materialType,
            string $rarity,
            string $desc,
            array $sourceTags = [],
            array $useTags = [],
            int $unlockLevel = 1,
            bool $canCompose = false,
            bool $canReforge = false,
        ): array {
            return [
                'id' => $id,
                'name' => $name,
                'type' => 'material',
                'sub_type' => $subType,
                'material_type' => $materialType,
                'rarity' => $rarity,
                'desc' => $desc,
                'drop_unlock_level' => $unlockLevel,
                'source_tags' => $sourceTags,
                'use_tags' => $useTags,
                'can_compose' => $canCompose,
                'can_reforge' => $canReforge,
            ];
        };

        $blueprint = fn (string $id, string $name, string $desc, array $sourceTags = [], array $useTags = []): array => [
            'id' => $id,
            'name' => $name,
            'type' => 'blueprint',
            'sub_type' => 'equipment_blueprint',
            'material_type' => 'blueprint',
            'rarity' => 'purple',
            'desc' => $desc,
            'source_tags' => $sourceTags,
            'use_tags' => $useTags,
        ];

        $fragment = fn (string $id, string $name, string $desc, array $sourceTags = [], array $useTags = []): array => [
            'id' => $id,
            'name' => $name,
            'type' => 'blueprint_fragment',
            'sub_type' => 'theme_blueprint_fragment',
            'material_type' => 'blueprint_fragment',
            'rarity' => 'blue',
            'desc' => $desc,
            'source_tags' => $sourceTags,
            'use_tags' => $useTags,
            'can_compose' => true,
        ];

        $item = fn (string $id, string $name, string $subType, string $rarity, string $desc, array $sourceTags = [], array $useTags = []): array => [
            'id' => $id,
            'name' => $name,
            'type' => 'item',
            'sub_type' => $subType,
            'material_type' => $subType,
            'rarity' => $rarity,
            'desc' => $desc,
            'source_tags' => $sourceTags,
            'use_tags' => $useTags,
        ];

        $gem = function (
            string $id,
            string $name,
            string $gemType,
            string $rarity,
            string $effectType,
            string $targetScope,
            array $payload,
            int $unlockLevel,
            array $socketLimit,
            string $desc,
            bool $canCompose = true,
            bool $canReforge = false,
            array $sourceTags = ['宝石副本', 'Boss掉落'],
        ): array {
            return [
                'id' => $id,
                'name' => $name,
                'type' => 'gem',
                'sub_type' => $gemType,
                'material_type' => 'gem',
                'rarity' => $rarity,
                'desc' => $desc,
                'effect_type' => $effectType,
                'target_scope' => $targetScope,
                'effect_payload' => $payload,
                'drop_unlock_level' => $unlockLevel,
                'socket_limit' => $socketLimit,
                'source_tags' => $sourceTags,
                'use_tags' => ['镶嵌', '宝石合成'],
                'can_compose' => $canCompose,
                'can_reforge' => $canReforge,
            ];
        };

        // 货币与兼容基础资源
        $add($currency('金币', '金币', '基础流通货币，用于打造、升星、升阶、洗练与商店消耗。', ['打造', '升星', '升阶', '洗练', '商店']));
        $add($item('宗门令', '宗门令', 'token', 'blue', '用于宗门商店兑换护身符与部分稀有道具。', ['宗门', '活动'], ['兑换']));
        $add($material('桂枝', '桂枝', 'forge_base', 'craft', 'white', '旧版打造与掉落兼容材料，仍保留给基础配置与调试逻辑使用。', ['旧版掉落'], ['打造']));
        $add($material('玉屑', '玉屑', 'forge_base', 'craft', 'white', '旧版白装回收与升星兼容材料。', ['分解', '旧版掉落'], ['升星', '打造']));
        $add($material('白玉碎', '白玉碎', 'forge_base', 'craft', 'blue', '中阶打造与升星兼容材料。', ['分解', '副本'], ['升星', '打造']));
        $add($material('妖核', '妖核', 'boss_core', 'boss', 'gold', '高阶 Boss 兼容核心材料，用于旧版与新版本共存配置。', ['Boss'], ['打造', '升星', '终章']));
        $add($item('打孔石', '打孔石', 'socket_tool', 'blue', '用于旧版打孔界面和兼容测试。', ['Boss', '礼包'], ['打孔']));
        $add($material('玄铁屑', '玄铁屑', 'forge_part', 'craft', 'blue', '稀有锻材，用于高阶锻造配方占位与后续扩展。', ['Boss', '副本'], ['打造']));
        $add($material('灵髓', '灵髓', 'forge_theme', 'craft', 'purple', '灵性浓缩物，用于高阶锻造与图纸系配方。', ['功能区', 'Boss'], ['打造', '图纸']));
        $add($material('南山玉印', '南山玉印', 'boss_mark', 'boss', 'purple', '南山系关键凭证，兼容旧版奖励与后续扩展。', ['终章', 'Boss'], ['兑换', '终章']));
        $add($item('蓝装回收符', '蓝装回收符', 'salvage_tool', 'blue', '用于蓝装回收提示与礼包投放。', ['礼包'], ['回收']));
        $add($item('护身符兑换凭证', '护身符兑换凭证', 'exchange_ticket', 'purple', '用于40级后护身符兑换引导。', ['礼包', 'Boss'], ['兑换']));
        $add($item('宝石副本入场令', '宝石副本入场令', 'dungeon_ticket', 'blue', '用于宝石副本额外挑战次数的占位道具。', ['礼包', '活动'], ['副本']));

        // 1—60基础打造材料
        foreach ([
            ['桂木', '木脉基础材料，用于招摇阶段的基础锻造。'],
            ['棪木', '堂庭矿林中的木材，是20级中期锻造的常用主材。'],
            ['迷榖枝', '迷榖树残枝，兼具避路与导灵用途。'],
            ['祝余叶', '祝余草叶，常用于前期兵甲与补给打造。'],
            ['赤金砂', '杻阳矿脉中的赤金碎砂，40级锻造主材。'],
            ['白金砂', '杻阳白金矿粉，防御向装备常用主材。'],
            ['白玉髓', '白玉矿心凝成的玉髓，用于饰品和套装辅材。'],
            ['水玉晶', '堂庭水玉结晶，兼具矿脉与水脉属性。'],
            ['青雘石', '青丘地脉中产出的高阶灵石，60级锻造与宝石核心辅材。'],
        ] as [$id, $desc]) {
            $add($material($id, $id, 'forge_base', 'craft', 'white', $desc, ['主线普通掉落'], ['打造', '套装']));
        }

        foreach ([
            ['鹿蜀纹角', '鹿蜀套主材，主要来自杻阳与鹿蜀王路线。'],
            ['旋龟甲片', '旋龟套主材，防御向装备的重要来源。'],
            ['鯥羽鳞', '裂渊异种之鳞羽，用于柢山阶段打造。'],
            ['类兽长髦', '亶爰之类的特殊毛束，用于秘禁系配方。'],
            ['猼訑残角', '基山秘藏中的异兽残角，可用于高阶主材配方。'],
            ['灌灌翎羽', '灌灌套系核心羽材。'],
            ['赤鱬珠', '赤鱬系装备与宝石的核心灵珠。'],
            ['九尾残绒', '九尾遗落的狐绒，是终章套装与图纸的重要材料。'],
            ['鹿蜀纹角碎片', '鹿蜀角材的前置碎片，用于普通地图掉落阶段。'],
            ['鹿蜀尾鬃', '鹿蜀王路线的辅材，用于40级套装锻造。'],
            ['裂渊石', '柢山裂谷中的深层矿石。'],
            ['深层白玉髓', '深层玉髓，常用于40—60级饰品与套装辅材。'],
            ['裂渊骨刺', '柢山精英与Boss的高阶掉材。'],
            ['深渊水晶', '深渊环境中析出的高纯水晶。'],
            ['镇脉灵骨', '箕尾封脉守影相关的高级材料。'],
        ] as [$id, $desc]) {
            $add($material($id, $id, 'forge_part', 'craft', 'blue', $desc, ['精英', 'Boss'], ['打造', '升阶']));
        }

        foreach ([
            ['鹊山残印', '序章残印，用于世界观与礼包投放。'],
            ['招摇祭骨', '招摇封脉相关关键材料。'],
            ['招摇祭骨碎片', '招摇祭骨的碎片形态，用于地图与Boss概率掉落。'],
            ['青丘封符', '青丘线关键封符，用于终章前置套装与剧情相关配方。'],
            ['箕尾镇脉石', '终章镇脉主材，60级主养成核心来源。'],
            ['育沛珠碎片', '狌狌线稀有素材碎片。'],
            ['白猿毛', '堂庭白猿相关材料。'],
            ['堂庭猿骨', '堂庭白猿高阶骨材。'],
            ['蝮毒囊', '猨翼毒脉产物，用于中期过渡锻材。'],
            ['猨翼骨片', '猨翼裂脉碎片，用于40级前铺垫。'],
            ['蝮牙', '猨翼蝮王路线稀有材料。'],
            ['怪蛇鳞', '猨翼蛇类材料，偏向敏捷/暴击路线。'],
            ['毒瘴结晶', '猨翼毒瘴环境凝结出的结晶。'],
            ['封脉残符', '终章封脉残符，用于终章配方和礼包。'],
            ['九尾残绒碎片', '九尾残绒的前置碎片。'],
            ['青丘封符碎片', '青丘封符的碎片形态。'],
            ['深泽水纹碎片', '旋龟线高阶图纸辅材。'],
            ['灵晶碎片', '基山秘藏产出的通用灵晶碎片。'],
            ['禁录残页', '亶爰秘禁中的古录残页。'],
            ['青丘前置残印', '青丘终章前置路线掉落的引导材料。'],
            ['紫金宝石碎片', '用于60级高阶宝石相关配方。'],
            ['终章材料', '终章通用高阶材料包内容物。'],
            ['狌狌爪', '狌狌精英素材。'],
            ['水玉髓', '堂庭矿脉精华。'],
            ['护身符兑换材料', '40级后护身符兑换体系的消耗材料。'],
            ['技能宝石碎片', '40级起的技能宝石碎片。'],
            ['高阶技能宝石碎片', '60级高阶技能宝石碎片。'],
            ['属性宝石碎片·白蓝', '低阶属性宝石碎片。'],
            ['紫/金宝石碎片', '高阶属性宝石碎片。'],
            ['基础打造材料', '早期普通打造使用的通用基础材料。'],
        ] as [$id, $desc]) {
            $add($material($id, $id, 'story_mat', 'story', 'blue', $desc, ['主线', 'Boss'], ['打造', '剧情', '礼包']));
        }

        // Boss印记与核心
        foreach ([
            ['招摇印记', '招摇主Boss稳定掉落印记。'],
            ['堂庭印记', '堂庭主Boss稳定掉落印记。'],
            ['猨翼印记', '猨翼主Boss稳定掉落印记。'],
            ['杻阳印记', '杻阳主Boss稳定掉落印记。'],
            ['旋龟印', '旋龟副Boss印记，同时保留作技能宝石同名体系。'],
            ['柢山印记', '柢山主Boss稳定掉落印记。'],
            ['亶爰禁印', '亶爰秘禁主Boss印记。'],
            ['基山秘印', '基山秘藏主Boss印记。'],
            ['青丘印记', '青丘终章印记。'],
            ['灌灌印', '灌灌精英Boss印记，同时与技能宝石世界观名同名。'],
            ['赤鱬印', '赤鱬事件Boss印记，同时与技能宝石世界观名同名。'],
            ['箕尾印记', '箕尾终章Boss印记。'],
            ['boss_mark_nanshan', '通用南山 Boss 印记，用于旧版与副本兼容。'],
            ['boss_mark_qingqiu', '通用青丘 Boss 印记，用于旧版与活动兼容。'],
            ['boss_mark_kunlun', '通用昆仑 Boss 印记占位。'],
        ] as [$id, $desc]) {
            $add($material($id, $id, 'boss_mark', 'boss', 'purple', $desc, ['Boss稳定掉落'], ['套装', '图纸', '兑换']));
        }

        foreach ([
            ['南山核心', '南山主题通用核心材料。'],
            ['青丘核心', '青丘终章核心材料。'],
            ['昆仑核心', '昆仑主题核心占位。'],
            ['旋龟核心', '旋龟系 Boss 核心。'],
            ['鯥王核心', '鯥王系 Boss 核心。'],
            ['镇脉核心', '箕尾镇脉核心。'],
            ['boss_core_nanshan', '通用南山 Boss 核心，占位兼容。'],
            ['boss_core_qingqiu', '通用青丘 Boss 核心，占位兼容。'],
            ['boss_core_kunlun', '通用昆仑 Boss 核心，占位兼容。'],
        ] as [$id, $desc]) {
            $add($material($id, $id, 'boss_core', 'boss', 'gold', $desc, ['Boss概率掉落'], ['终章打造', '高阶配方']));
        }

        // 升星与洗练
        $add($material('star_stone_t1_common', '初阶星材', 'star', 'star', 'white', '用于1—3星升星。', ['星材副本', '礼包'], ['升星']));
        $add($material('star_stone_t2_common', '中阶星材', 'star', 'star', 'blue', '用于4—6星升星。', ['星材副本', '礼包'], ['升星']));
        $add($material('star_stone_t3_common', '高阶星材', 'star', 'star', 'purple', '用于7—8星升星。', ['星材副本', '礼包'], ['升星']));
        $add($material('star_stone_t4_common', '极阶星材', 'star', 'star', 'gold', '用于9—10星升星。', ['星材副本', '礼包'], ['升星']));
        $add($item('star_material_t1_pack', '初阶星材礼包', 'pack', 'blue', '打开后获得一批初阶星材。', ['礼包'], ['开启'])) ;
        $add($material('refine_sand_basic', '洗练石', 'refine', 'refine', 'blue', '50级开放的基础洗练材料。', ['洗练副本', '礼包'], ['洗练'], 50));
        $add($material('refine_core_advanced', '洗练精华', 'refine', 'refine', 'purple', '高阶洗练材料，用于紫色洗练相关系统。', ['洗练副本', 'Boss'], ['洗练'], 50));
        $add($material('equipment_essence', '装备精华', 'refine', 'refine', 'purple', '用于洗练与高阶装备成长。', ['分解', '洗练副本'], ['洗练', '成长'], 50));
        $add($material('purple_refine_dust', '紫洗练材料', 'refine', 'refine', 'gold', '60级后用于高阶紫色洗练与终章 build 微调。', ['洗练副本', '礼包'], ['洗练'], 60));

        // 图纸碎片
        foreach ([
            ['bp_fragment_nanshan', '南山图纸碎片', '南山主题通用图纸碎片。'],
            ['bp_fragment_qingqiu', '青丘图纸碎片', '青丘主题通用图纸碎片。'],
            ['bp_fragment_kunlun', '昆仑图纸碎片', '昆仑主题通用图纸碎片。'],
            ['白猿王图纸碎片', '白猿王图纸碎片', '堂庭路线图纸碎片。'],
            ['猨翼图纸碎片', '猨翼图纸碎片', '猨翼路线图纸碎片。'],
        ] as [$id, $name, $desc]) {
            $add($fragment($id, $name, $desc, ['Boss概率掉落', '图纸副本'], ['图纸合成']));
        }

        // 图纸本体
        foreach ([
            ['bp_weapon_nanshan_t1_01', '20级主武器图纸', '20级主武器打造图纸。'],
            ['bp_ring_nanshan_t2_01', '40级戒指图纸', '40级戒指打造图纸。'],
            ['bp_bracelet_qingqiu_t2_01', '40级手镯图纸', '40级手镯打造图纸。'],
            ['bp_cloak_qingqiu_t2_01', '40级披风图纸', '兼容旧命名的图纸占位。'],
            ['鹿蜀王图纸', '鹿蜀王图纸', '杻阳主Boss低概率掉落的完整图纸。'],
            ['九尾妖影图纸', '九尾妖影图纸', '青丘终章主Boss掉落图纸。'],
            ['60级主武器图纸', '60级主武器图纸', '箕尾终章相关主武器图纸。'],
            ['bp_set_lushu_40', '40级鹿蜀套图纸', '40级鹿蜀套装通用图纸。'],
            ['bp_set_xuangui_40', '40级旋龟套图纸', '40级旋龟套装通用图纸。'],
            ['bp_set_guanguan_40', '40级灌灌套图纸', '40级灌灌套装通用图纸。'],
            ['bp_set_migu_40', '40级迷榖套图纸', '40级迷榖套装通用图纸。'],
            ['bp_set_chiyu_40', '40级赤鱬套图纸', '40级赤鱬套装通用图纸。'],
            ['bp_set_qingqiu_40', '40级青丘套图纸', '40级青丘套装通用图纸。'],
            ['bp_set_lushu_60', '60级鹿蜀套图纸', '60级鹿蜀套装通用图纸。'],
            ['bp_set_xuangui_60', '60级旋龟套图纸', '60级旋龟套装通用图纸。'],
            ['bp_set_guanguan_60', '60级灌灌套图纸', '60级灌灌套装通用图纸。'],
            ['bp_set_migu_60', '60级迷榖套图纸', '60级迷榖套装通用图纸。'],
            ['bp_set_chiyu_60', '60级赤鱬套图纸', '60级赤鱬套装通用图纸。'],
            ['bp_set_qingqiu_60', '60级青丘套图纸', '60级青丘套装通用图纸。'],
        ] as [$id, $name, $desc]) {
            $add($blueprint($id, $name, $desc, ['Boss概率掉落', '图纸合成'], ['打造'])) ;
        }

        // 礼包与功能道具
        foreach ([
            ['box_weapon_set_20_select', '20级套装主武器自选箱', '可自选一把20级主养成套装主武器。'],
            ['pack_set_20_parts', '20级套装其他部位材料包', '打开后获得20级套装其余部位打造材料。'],
            ['box_weapon_blueprint_60_select', '60级套装主武器图纸自选箱', '可自选一张60级主武器图纸。'],
            ['box_attr_gem_blue_random', '蓝色属性宝石随机箱', '打开后随机获得一枚蓝色属性宝石。'],
            ['box_attr_gem_purple_random', '紫色属性宝石随机箱', '打开后随机获得一枚紫色属性宝石。'],
            ['box_attr_gem_gold_random', '金色属性宝石随机箱', '打开后随机获得一枚金色属性宝石。'],
            ['box_skill_gem_blue_random', '蓝色技能宝石随机箱', '打开后随机获得一枚蓝色技能宝石。'],
            ['box_skill_gem_purple_random', '紫色技能宝石随机箱', '打开后随机获得一枚紫色技能宝石。'],
            ['box_skill_gem_gold_random', '金色技能宝石随机箱', '打开后随机获得一枚金色技能宝石。'],
            ['20级蓝装', '20级蓝装', '20级蓝装掉落凭证或随机箱。'],
            ['30/40级蓝装', '30/40级蓝装', '30—40级蓝装掉落凭证或随机箱。'],
            ['40级蓝装', '40级蓝装', '40级蓝装掉落凭证或随机箱。'],
            ['60级蓝装', '60级蓝装', '60级蓝装掉落凭证或随机箱。'],
            ['蓝词条胚子装', '蓝词条胚子装', '可提取蓝词条的来源蓝装。'],
            ['招摇首通礼', '招摇首通礼', '招摇地图首通礼包。'],
            ['堂庭首通礼', '堂庭首通礼', '堂庭地图首通礼包。'],
            ['猨翼首通礼', '猨翼首通礼', '猨翼地图首通礼包。'],
            ['杻阳首通礼', '杻阳首通礼', '杻阳地图首通礼包。'],
            ['柢山首通礼', '柢山首通礼', '柢山裂渊首通礼包。'],
            ['青丘首通礼', '青丘首通礼', '青丘主线首通礼包。'],
            ['箕尾封脉礼', '箕尾封脉礼', '箕尾地图首通礼包。'],
            ['招摇首通奖励包', '招摇首通奖励包', '狌狌王首通奖励包。'],
            ['堂庭首通奖励包', '堂庭首通奖励包', '堂庭白猿王首通奖励包。'],
            ['猨翼首通奖励包', '猨翼首通奖励包', '猨翼蝮王首通奖励包。'],
            ['杻阳首通奖励包', '杻阳首通奖励包', '鹿蜀王首通奖励包。'],
            ['旋龟守脉礼', '旋龟守脉礼', '旋龟副Boss首通奖励包。'],
            ['柢山首通奖励包', '柢山首通奖励包', '鯥王首通奖励包。'],
            ['亶爰秘禁奖励包', '亶爰秘禁奖励包', '亶爰之类首通奖励包。'],
            ['基山秘藏奖励包', '基山秘藏奖励包', '猼訑首通奖励包。'],
            ['青丘终章奖励包', '青丘终章奖励包', '青丘九尾妖影首通奖励包。'],
            ['灌灌首通奖励包', '灌灌首通奖励包', '灌灌精英Boss首通奖励包。'],
            ['赤鱬首通奖励包', '赤鱬首通奖励包', '赤鱬事件Boss首通奖励包。'],
            ['箕尾封脉终礼', '箕尾封脉终礼', '箕尾终章Boss首通奖励包。'],
            ['灵晶秘匣', '灵晶秘匣', '基山秘藏中的灵晶奖励匣。'],
            ['白蓝属性宝石', '白蓝属性宝石', '低阶属性宝石随机奖励包。'],
            ['属性宝石', '属性宝石', '通用属性宝石奖励包。'],
            ['高阶属性宝石', '高阶属性宝石', '高阶属性宝石奖励包。'],
        ] as [$id, $name, $desc]) {
            $add($item($id, $name, 'pack', str_contains($name, '60级') || str_contains($name, '高阶') ? 'gold' : 'blue', $desc, ['礼包', 'Boss'], ['开启', '奖励'])) ;
        }

        // 兼容旧宝石ID
        $add($gem('赤晶石', '赤晶石', 'attr', 'white', 'stat', 'global', ['stat' => 'ATK', 'value' => 2], 1, [1, 2], '兼容旧版的攻击属性宝石。'));
        $add($gem('沧澜石', '沧澜石', 'attr', 'white', 'stat', 'global', ['stat' => 'HP', 'value' => 12], 1, [1, 2], '兼容旧版的生命属性宝石。'));
        $add($gem('青木石', '青木石', 'attr', 'white', 'stat', 'global', ['stat' => 'DEF', 'value' => 2], 1, [1, 2], '兼容旧版的防御属性宝石。'));
        $add($gem('裂石符玉', '裂石符玉', 'skill', 'blue', 'skill_modifier', 'skill_id', ['skill_id' => 'BING_01', 'modifier' => 'damage_up', 'value' => 0.08], 40, [3, 4], '兼容旧版的技能宝石。', true, false, ['Boss掉落', '宝石副本']));

        // 属性宝石
        $attrGems = [
            ['赤金石', 'ATK', 6, 'white', 40],
            ['白玉晶', 'HP', 28, 'white', 40],
            ['青雘珠', 'DEF', 6, 'white', 40],
            ['鹿蜀纹石', 'CRIT_RATE', 2, 'blue', 40],
            ['狌狌血玉', 'ATK_SPEED', 3, 'blue', 20],
            ['迷榖风晶', 'DODGE', 3, 'blue', 20],
            ['祝余灵晶', 'HP', 48, 'blue', 20],
            ['育沛珠', 'SPELL_ATK', 8, 'blue', 20],
            ['英水灵珠', 'RANGED_ATK', 8, 'purple', 40],
            ['赤鱬内珠', 'FINAL_DAMAGE', 2, 'purple', 50],
            ['旋龟甲石', 'PDEF', 10, 'purple', 40],
            ['白金髓', 'MDEF', 10, 'gold', 60],
            ['灌灌羽晶', 'CRIT_DMG', 6, 'gold', 60],
        ];
        foreach ($attrGems as [$id, $stat, $value, $rarity, $unlock]) {
            $add($gem($id, $id, 'attr', $rarity, 'stat', 'global', ['stat' => $stat, 'value' => $value], $unlock, [1, 2], sprintf('%s型属性宝石，可镶嵌于第1/2孔。', $id), true, in_array($rarity, ['purple', 'gold'], true)));
        }

        // 技能宝石
        $skillGems = [
            ['gem_skill_xingxing_seal', '狌狌印', 'BING_01', 'range_up', 0.08, 'blue', 40],
            ['gem_skill_lushu_seal', '鹿蜀印', 'BING_01', 'crit_up', 0.10, 'blue', 40],
            ['gem_skill_xuangui_seal', '旋龟印', 'BING_01', 'shield_up', 0.12, 'blue', 40],
            ['gem_skill_guanguan_seal', '灌灌印', 'BING_01', 'cooldown_down', 0.06, 'purple', 50],
            ['gem_skill_migu_seal', '迷榖印', 'BING_01', 'duration_up', 0.12, 'purple', 50],
            ['gem_skill_chiyu_seal', '赤鱬印', 'BING_01', 'burn_up', 0.15, 'purple', 50],
            ['gem_skill_qingqiu_seal', '青丘印', 'BING_01', 'slow_up', 0.12, 'gold', 60],
            ['gem_skill_yingshui_seal', '英水印', 'BING_01', 'mana_cost_down', 0.10, 'gold', 60],
            ['gem_skill_jiwei_seal', '箕尾镇印', 'BING_01', 'damage_up', 0.18, 'gold', 60],
        ];
        foreach ($skillGems as [$id, $name, $skillId, $modifier, $value, $rarity, $unlock]) {
            $add($gem($id, $name, 'skill', $rarity, 'skill_modifier', 'skill_id', ['skill_id' => $skillId, 'modifier' => $modifier, 'value' => $value], $unlock, [3, 4], sprintf('%s型技能宝石，可镶嵌于第3/4孔。', $name), true, in_array($rarity, ['purple', 'gold'], true)));
        }

        foreach ($rows as $row) {
            Item::query()->updateOrCreate(['id' => $row['id']], $row);
        }
    }
}
