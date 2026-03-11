<?php

namespace Database\Seeders;

use App\Models\StoryMapDrop;
use Illuminate\Database\Seeder;

class StoryMapDropsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // 鹊山将启
            $this->drop('mapdrop_quque_01', 'map_quque_start', '普通', '桂木', '桂木', '材料', 1, 2, 0.55, false, '序章基础木材掉落', 1),
            $this->drop('mapdrop_quque_02', 'map_quque_start', '普通', '祝余叶', '祝余叶', '材料', 1, 2, 0.45, false, '序章基础草药掉落', 2),
            $this->drop('mapdrop_quque_03', 'map_quque_start', '普通', '金币', '金币', '金币', 40, 80, 1.0, false, '序章战斗基础金币', 3),
            $this->drop('mapdrop_quque_04', 'map_quque_start', '首通奖励', '鹊山残印', '鹊山残印', '材料', 1, 1, 1.0, true, '鹊山将启首通奖励', 4),

            // 招摇之山
            $this->drop('mapdrop_zhaoyao_01', 'map_zhaoyao', '普通', '桂木', '桂木', '材料', 2, 4, 0.65, false, '招摇林区基础采集材料', 10),
            $this->drop('mapdrop_zhaoyao_02', 'map_zhaoyao', '普通', '祝余叶', '祝余叶', '材料', 2, 4, 0.55, false, '招摇灵草掉落', 20),
            $this->drop('mapdrop_zhaoyao_03', 'map_zhaoyao', '普通', '迷榖枝', '迷榖枝', '材料', 1, 3, 0.45, false, '招摇木脉掉落', 30),
            $this->drop('mapdrop_zhaoyao_04', 'map_zhaoyao', '普通', '金币', '金币', '金币', 80, 140, 1.0, false, '普通战斗基础金币', 40),
            $this->drop('mapdrop_zhaoyao_05', 'map_zhaoyao', '精英', '狌狌爪', '狌狌爪', '材料', 1, 2, 0.38, false, '招摇精英掉落', 50),
            $this->drop('mapdrop_zhaoyao_06', 'map_zhaoyao', '精英', '育沛珠碎片', '育沛珠碎片', '材料', 1, 2, 0.24, false, '招摇精英稀有掉落', 60),
            $this->drop('mapdrop_zhaoyao_07', 'map_zhaoyao', 'Boss稳定', '招摇印记', '招摇印记', '材料', 1, 1, 1.0, false, '招摇Boss稳定掉落', 70),
            $this->drop('mapdrop_zhaoyao_08', 'map_zhaoyao', 'Boss概率', '狌狌血玉', '狌狌血玉', '宝石', 1, 1, 0.22, false, '招摇Boss中概率掉落', 80),
            $this->drop('mapdrop_zhaoyao_09', 'map_zhaoyao', 'Boss概率', '招摇祭骨碎片', '招摇祭骨碎片', '材料', 1, 2, 0.18, false, '招摇Boss稀有掉落', 90),
            $this->drop('mapdrop_zhaoyao_10', 'map_zhaoyao', 'Boss概率', '属性宝石碎片·白蓝', '属性宝石碎片·白蓝', '宝石', 1, 2, 0.16, false, '招摇Boss宝石掉落', 100),
            $this->drop('mapdrop_zhaoyao_11', 'map_zhaoyao', '首通奖励', '招摇首通礼', '招摇首通礼', '礼包道具', 1, 1, 1.0, true, '招摇之山首通奖励', 110),

            // 堂庭之山
            $this->drop('mapdrop_tangting_01', 'map_tangting', '普通', '棪木', '棪木', '材料', 2, 4, 0.62, false, '堂庭基础采集材料', 120),
            $this->drop('mapdrop_tangting_02', 'map_tangting', '普通', '水玉晶', '水玉晶', '材料', 1, 3, 0.48, false, '堂庭矿脉掉落', 130),
            $this->drop('mapdrop_tangting_03', 'map_tangting', '普通', '基础打造材料', '基础打造材料', '材料', 1, 2, 0.40, false, '堂庭普通打造材料', 140),
            $this->drop('mapdrop_tangting_04', 'map_tangting', '精英', '白猿毛', '白猿毛', '材料', 1, 2, 0.34, false, '堂庭精英掉落', 150),
            $this->drop('mapdrop_tangting_05', 'map_tangting', '精英', '堂庭猿骨', '堂庭猿骨', '材料', 1, 1, 0.20, false, '堂庭精英稀有掉落', 160),
            $this->drop('mapdrop_tangting_06', 'map_tangting', 'Boss稳定', '堂庭印记', '堂庭印记', '印记', 1, 1, 1.0, false, '堂庭Boss稳定掉落', 170),
            $this->drop('mapdrop_tangting_07', 'map_tangting', 'Boss概率', '白猿王图纸碎片', '白猿王图纸碎片', '图纸', 1, 2, 0.20, false, '堂庭Boss图纸碎片', 180),
            $this->drop('mapdrop_tangting_08', 'map_tangting', 'Boss概率', '水玉髓', '水玉髓', '材料', 1, 2, 0.24, false, '堂庭Boss矿脉稀有材料', 190),
            $this->drop('mapdrop_tangting_09', 'map_tangting', 'Boss概率', '20级蓝装', '20级蓝装', '蓝装', 1, 1, 0.12, false, '堂庭Boss可掉20级蓝装', 200),
            $this->drop('mapdrop_tangting_10', 'map_tangting', '首通奖励', '堂庭首通礼', '堂庭首通礼', '礼包道具', 1, 1, 1.0, true, '堂庭之山首通奖励', 210),

            // 猨翼之山
            $this->drop('mapdrop_yuanyi_01', 'map_yuanyi', '普通', '白玉髓', '白玉髓', '材料', 2, 4, 0.55, false, '猨翼普通掉落', 220),
            $this->drop('mapdrop_yuanyi_02', 'map_yuanyi', '普通', '蝮毒囊', '蝮毒囊', '材料', 1, 2, 0.40, false, '猨翼毒脉材料', 230),
            $this->drop('mapdrop_yuanyi_03', 'map_yuanyi', '普通', '猨翼骨片', '猨翼骨片', '材料', 1, 3, 0.36, false, '猨翼裂脉碎片', 240),
            $this->drop('mapdrop_yuanyi_04', 'map_yuanyi', '精英', '蝮牙', '蝮牙', '材料', 1, 2, 0.30, false, '猨翼精英掉落', 250),
            $this->drop('mapdrop_yuanyi_05', 'map_yuanyi', '精英', '怪蛇鳞', '怪蛇鳞', '材料', 1, 2, 0.24, false, '猨翼精英掉落', 260),
            $this->drop('mapdrop_yuanyi_06', 'map_yuanyi', 'Boss稳定', '猨翼印记', '猨翼印记', '印记', 1, 1, 1.0, false, '猨翼Boss稳定掉落', 270),
            $this->drop('mapdrop_yuanyi_07', 'map_yuanyi', 'Boss概率', '猨翼图纸碎片', '猨翼图纸碎片', '图纸', 1, 2, 0.22, false, '猨翼Boss图纸碎片', 280),
            $this->drop('mapdrop_yuanyi_08', 'map_yuanyi', 'Boss概率', '毒瘴结晶', '毒瘴结晶', '材料', 1, 2, 0.20, false, '猨翼Boss稀有材料', 290),
            $this->drop('mapdrop_yuanyi_09', 'map_yuanyi', 'Boss概率', '20级蓝装', '20级蓝装', '蓝装', 1, 1, 0.15, false, '猨翼Boss蓝装掉落', 300),
            $this->drop('mapdrop_yuanyi_10', 'map_yuanyi', 'Boss概率', '蓝词条胚子装', '蓝词条胚子装', '装备', 1, 1, 0.10, false, '猨翼Boss蓝词条来源装备', 310),
            $this->drop('mapdrop_yuanyi_11', 'map_yuanyi', '首通奖励', '猨翼首通礼', '猨翼首通礼', '礼包道具', 1, 1, 1.0, true, '猨翼之山首通奖励', 320),

            // 杻阳之山
            $this->drop('mapdrop_niuyang_01', 'map_niuyang', '普通', '赤金砂', '赤金砂', '材料', 2, 4, 0.58, false, '杻阳普通掉落', 330),
            $this->drop('mapdrop_niuyang_02', 'map_niuyang', '普通', '白金砂', '白金砂', '材料', 2, 4, 0.58, false, '杻阳普通掉落', 340),
            $this->drop('mapdrop_niuyang_03', 'map_niuyang', '普通', '鹿蜀纹角碎片', '鹿蜀纹角碎片', '材料', 1, 2, 0.24, false, '杻阳普通稀有掉落', 350),
            $this->drop('mapdrop_niuyang_04', 'map_niuyang', '精英', '旋龟甲片', '旋龟甲片', '材料', 1, 2, 0.28, false, '杻阳精英掉落', 360),
            $this->drop('mapdrop_niuyang_05', 'map_niuyang', '精英', '鹿蜀尾鬃', '鹿蜀尾鬃', '材料', 1, 2, 0.22, false, '杻阳精英掉落', 370),
            $this->drop('mapdrop_niuyang_06', 'map_niuyang', 'Boss稳定', '杻阳印记', '杻阳印记', '印记', 1, 1, 1.0, false, '杻阳Boss稳定掉落', 380),
            $this->drop('mapdrop_niuyang_07', 'map_niuyang', 'Boss概率', '鹿蜀王图纸', '鹿蜀王图纸', '图纸', 1, 1, 0.12, false, '杻阳Boss完整图纸', 390),
            $this->drop('mapdrop_niuyang_08', 'map_niuyang', 'Boss概率', '旋龟核心', '旋龟核心', '材料', 1, 1, 0.08, false, '杻阳Boss核心材料', 400),
            $this->drop('mapdrop_niuyang_09', 'map_niuyang', 'Boss概率', '30/40级蓝装', '30/40级蓝装', '蓝装', 1, 1, 0.14, false, '杻阳Boss中后期蓝装', 410),
            $this->drop('mapdrop_niuyang_10', 'map_niuyang', 'Boss极低概率', '属性宝石', '属性宝石', '宝石', 1, 1, 0.04, false, '杻阳Boss极低概率属性宝石', 420),
            $this->drop('mapdrop_niuyang_11', 'map_niuyang', '首通奖励', '杻阳首通礼', '杻阳首通礼', '礼包道具', 1, 1, 1.0, true, '杻阳之山首通奖励', 430),

            // 柢山裂渊
            $this->drop('mapdrop_dishan_01', 'map_dishan', '普通', '鯥羽鳞', '鯥羽鳞', '材料', 2, 4, 0.52, false, '柢山普通掉落', 440),
            $this->drop('mapdrop_dishan_02', 'map_dishan', '普通', '裂渊石', '裂渊石', '材料', 1, 3, 0.42, false, '裂渊矿石', 450),
            $this->drop('mapdrop_dishan_03', 'map_dishan', '普通', '深层白玉髓', '深层白玉髓', '材料', 1, 2, 0.28, false, '柢山深层玉髓', 460),
            $this->drop('mapdrop_dishan_04', 'map_dishan', '精英', '裂渊骨刺', '裂渊骨刺', '材料', 1, 2, 0.26, false, '柢山精英掉落', 470),
            $this->drop('mapdrop_dishan_05', 'map_dishan', '精英', '深渊水晶', '深渊水晶', '材料', 1, 2, 0.20, false, '柢山精英稀有掉落', 480),
            $this->drop('mapdrop_dishan_06', 'map_dishan', 'Boss稳定', '柢山印记', '柢山印记', '印记', 1, 1, 1.0, false, '柢山Boss稳定掉落', 490),
            $this->drop('mapdrop_dishan_07', 'map_dishan', 'Boss概率', '鯥王核心', '鯥王核心', '核心', 1, 1, 0.10, false, '柢山Boss核心材料', 500),
            $this->drop('mapdrop_dishan_08', 'map_dishan', 'Boss概率', '护身符兑换材料', '护身符兑换材料', '材料', 1, 2, 0.18, false, '柢山Boss功能系统材料', 510),
            $this->drop('mapdrop_dishan_09', 'map_dishan', 'Boss概率', '技能宝石碎片', '技能宝石碎片', '宝石', 1, 3, 0.22, false, '柢山Boss技能宝石碎片', 520),
            $this->drop('mapdrop_dishan_10', 'map_dishan', '首通奖励', '柢山首通礼', '柢山首通礼', '礼包道具', 1, 1, 1.0, true, '柢山裂渊首通奖励', 530),

            // 亶爰秘禁
            $this->drop('mapdrop_tanyuan_01', 'map_tanyuan', '普通', '禁录残页', '禁录残页', '材料', 1, 2, 0.42, false, '亶爰秘禁普通掉落', 531),
            $this->drop('mapdrop_tanyuan_02', 'map_tanyuan', '普通', '类兽长髦', '类兽长髦', '材料', 1, 2, 0.28, false, '亶爰秘禁普通掉落', 532),
            $this->drop('mapdrop_tanyuan_03', 'map_tanyuan', '精英', '灵晶碎片', '灵晶碎片', '材料', 1, 2, 0.22, false, '亶爰秘禁精英掉落', 533),
            $this->drop('mapdrop_tanyuan_04', 'map_tanyuan', 'Boss稳定', '亶爰禁印', '亶爰禁印', '印记', 1, 1, 1.0, false, '亶爰Boss稳定掉落', 534),
            $this->drop('mapdrop_tanyuan_05', 'map_tanyuan', 'Boss概率', '禁录残页', '禁录残页', '材料', 1, 2, 0.25, false, '亶爰Boss功能材料', 535),
            $this->drop('mapdrop_tanyuan_06', 'map_tanyuan', 'Boss概率', 'bp_fragment_nanshan', '南山图纸碎片', '图纸', 1, 2, 0.18, false, '亶爰Boss图纸碎片', 536),
            $this->drop('mapdrop_tanyuan_07', 'map_tanyuan', '首通奖励', '亶爰秘禁奖励包', '亶爰秘禁奖励包', '礼包道具', 1, 1, 1.0, true, '亶爰秘禁首通奖励', 537),

            // 基山秘藏
            $this->drop('mapdrop_jishan_01', 'map_jishan', '普通', '灵晶碎片', '灵晶碎片', '材料', 1, 3, 0.44, false, '基山秘藏普通掉落', 538),
            $this->drop('mapdrop_jishan_02', 'map_jishan', '普通', '猼訑残角', '猼訑残角', '材料', 1, 2, 0.26, false, '基山秘藏普通掉落', 539),
            $this->drop('mapdrop_jishan_03', 'map_jishan', '精英', '青雘石', '青雘石', '材料', 1, 2, 0.18, false, '基山秘藏精英掉落', 540),
            $this->drop('mapdrop_jishan_04', 'map_jishan', 'Boss稳定', '基山秘印', '基山秘印', '印记', 1, 1, 1.0, false, '基山Boss稳定掉落', 541),
            $this->drop('mapdrop_jishan_05', 'map_jishan', 'Boss概率', '灵晶秘匣', '灵晶秘匣', '礼包道具', 1, 1, 0.16, false, '基山Boss灵晶秘匣', 542),
            $this->drop('mapdrop_jishan_06', 'map_jishan', 'Boss极低概率', '高阶属性宝石', '高阶属性宝石', '宝石', 1, 1, 0.05, false, '基山Boss高阶属性宝石', 543),
            $this->drop('mapdrop_jishan_07', 'map_jishan', '首通奖励', '基山秘藏奖励包', '基山秘藏奖励包', '礼包道具', 1, 1, 1.0, true, '基山秘藏首通奖励', 544),

            // 青丘之山
            $this->drop('mapdrop_qingqiu_01', 'map_qingqiu', '普通', '青雘石', '青雘石', '材料', 2, 4, 0.50, false, '青丘普通掉落', 540),
            $this->drop('mapdrop_qingqiu_02', 'map_qingqiu', '普通', '灌灌翎羽', '灌灌翎羽', '材料', 1, 3, 0.36, false, '青丘普通掉落', 550),
            $this->drop('mapdrop_qingqiu_03', 'map_qingqiu', '普通', '赤鱬珠', '赤鱬珠', '材料', 1, 2, 0.30, false, '青丘普通掉落', 560),
            $this->drop('mapdrop_qingqiu_04', 'map_qingqiu', '精英', '九尾残绒碎片', '九尾残绒碎片', '材料', 1, 2, 0.24, false, '青丘精英掉落', 570),
            $this->drop('mapdrop_qingqiu_05', 'map_qingqiu', '精英', '青丘封符碎片', '青丘封符碎片', '材料', 1, 2, 0.22, false, '青丘精英掉落', 580),
            $this->drop('mapdrop_qingqiu_06', 'map_qingqiu', 'Boss稳定', '青丘印记', '青丘印记', '印记', 1, 1, 1.0, false, '青丘Boss稳定掉落', 590),
            $this->drop('mapdrop_qingqiu_07', 'map_qingqiu', 'Boss概率', '九尾妖影图纸', '九尾妖影图纸', '图纸', 1, 1, 0.10, false, '青丘Boss完整图纸', 600),
            $this->drop('mapdrop_qingqiu_08', 'map_qingqiu', 'Boss概率', '青丘核心', '青丘核心', '核心', 1, 1, 0.08, false, '青丘Boss核心材料', 610),
            $this->drop('mapdrop_qingqiu_09', 'map_qingqiu', 'Boss概率', '紫/金宝石碎片', '紫/金宝石碎片', '宝石', 1, 2, 0.18, false, '青丘Boss高阶宝石碎片', 620),
            $this->drop('mapdrop_qingqiu_10', 'map_qingqiu', 'Boss概率', '60级蓝装', '60级蓝装', '蓝装', 1, 1, 0.10, false, '青丘Boss高阶蓝装', 630),
            $this->drop('mapdrop_qingqiu_11', 'map_qingqiu', '首通奖励', '青丘首通礼', '青丘首通礼', '礼包道具', 1, 1, 1.0, true, '青丘之山首通奖励', 640),

            // 箕尾之山
            $this->drop('mapdrop_jiwei_01', 'map_jiwei', '普通', '箕尾镇脉石碎片', '箕尾镇脉石碎片', '材料', 1, 3, 0.42, false, '箕尾普通掉落', 650),
            $this->drop('mapdrop_jiwei_02', 'map_jiwei', '普通', '白玉髓', '白玉髓', '材料', 1, 2, 0.36, false, '箕尾普通掉落', 660),
            $this->drop('mapdrop_jiwei_03', 'map_jiwei', '普通', '封脉残符', '封脉残符', '材料', 1, 2, 0.30, false, '箕尾普通掉落', 670),
            $this->drop('mapdrop_jiwei_04', 'map_jiwei', '精英', '镇脉灵骨', '镇脉灵骨', '材料', 1, 2, 0.24, false, '箕尾精英掉落', 680),
            $this->drop('mapdrop_jiwei_05', 'map_jiwei', 'Boss稳定', '箕尾印记', '箕尾印记', '印记', 1, 1, 1.0, false, '箕尾Boss稳定掉落', 690),
            $this->drop('mapdrop_jiwei_06', 'map_jiwei', 'Boss概率', '镇脉核心', '镇脉核心', '核心', 1, 1, 0.10, false, '箕尾Boss核心材料', 700),
            $this->drop('mapdrop_jiwei_07', 'map_jiwei', 'Boss概率', '60级主武器图纸', '60级主武器图纸', '图纸', 1, 1, 0.08, false, '箕尾Boss主武器图纸', 710),
            $this->drop('mapdrop_jiwei_08', 'map_jiwei', 'Boss概率', '高阶技能宝石碎片', '高阶技能宝石碎片', '宝石', 1, 2, 0.16, false, '箕尾Boss高阶技能宝石碎片', 720),
            $this->drop('mapdrop_jiwei_09', 'map_jiwei', 'Boss概率', '终章材料', '终章材料', '材料', 1, 2, 0.14, false, '箕尾Boss终章材料', 730),
            $this->drop('mapdrop_jiwei_10', 'map_jiwei', '首通奖励', '箕尾封脉礼', '箕尾封脉礼', '礼包道具', 1, 1, 1.0, true, '箕尾之山首通奖励', 740),
        ];

        foreach ($rows as $row) {
            StoryMapDrop::query()->updateOrCreate(
                ['drop_id' => $row['drop_id']],
                $row,
            );
        }
    }

    private function drop(
        string $dropId,
        string $mapId,
        string $dropTier,
        string $itemId,
        string $itemName,
        string $itemType,
        int $countMin,
        int $countMax,
        float $probability,
        bool $firstClearOnly,
        string $sourceDesc,
        int $sortOrder,
    ): array {
        return [
            'drop_id' => $dropId,
            'map_id' => $mapId,
            'drop_tier' => $dropTier,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'item_type' => $itemType,
            'count_min' => $countMin,
            'count_max' => $countMax,
            'probability' => $probability,
            'first_clear_only' => $firstClearOnly,
            'source_desc' => $sourceDesc,
            'icon_path' => '',
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }
}
