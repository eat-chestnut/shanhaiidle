<?php

namespace Database\Seeders;

use App\Models\StoryBossDrop;
use Illuminate\Database\Seeder;

class StoryBossDropsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->drop('bossdrop_zhaoyao_01', 'boss_zhaoyao_xingxing', '稳定掉落', '招摇印记', '招摇印记', '印记', 1, 1, 1.0, false, '招摇主Boss稳定掉落', 10),
            $this->drop('bossdrop_zhaoyao_02', 'boss_zhaoyao_xingxing', '概率掉落', '狌狌血玉', '狌狌血玉', '宝石', 1, 1, 0.24, false, '招摇Boss稀有宝石', 20),
            $this->drop('bossdrop_zhaoyao_03', 'boss_zhaoyao_xingxing', '概率掉落', '招摇祭骨', '招摇祭骨', '材料', 1, 2, 0.20, false, '招摇Boss祭骨材料', 30),
            $this->drop('bossdrop_zhaoyao_04', 'boss_zhaoyao_xingxing', '极低概率掉落', '白蓝属性宝石', '白蓝属性宝石', '宝石', 1, 1, 0.05, false, '招摇Boss极低概率宝石', 40),
            $this->drop('bossdrop_zhaoyao_05', 'boss_zhaoyao_xingxing', '首通奖励', '招摇首通奖励包', '招摇首通奖励包', '礼包道具', 1, 1, 1.0, true, '招摇Boss首通奖励', 50),

            $this->drop('bossdrop_tangting_01', 'boss_tangting_ape', '稳定掉落', '堂庭印记', '堂庭印记', '印记', 1, 1, 1.0, false, '堂庭主Boss稳定掉落', 60),
            $this->drop('bossdrop_tangting_02', 'boss_tangting_ape', '概率掉落', '白猿王图纸碎片', '白猿王图纸碎片', '图纸', 1, 2, 0.22, false, '堂庭Boss图纸碎片', 70),
            $this->drop('bossdrop_tangting_03', 'boss_tangting_ape', '概率掉落', '水玉髓', '水玉髓', '材料', 1, 2, 0.24, false, '堂庭Boss矿材', 80),
            $this->drop('bossdrop_tangting_04', 'boss_tangting_ape', '极低概率掉落', '20级蓝装', '20级蓝装', '蓝装', 1, 1, 0.08, false, '堂庭Boss蓝装掉落', 90),
            $this->drop('bossdrop_tangting_05', 'boss_tangting_ape', '首通奖励', '堂庭首通奖励包', '堂庭首通奖励包', '礼包道具', 1, 1, 1.0, true, '堂庭Boss首通奖励', 100),

            $this->drop('bossdrop_yuanyi_01', 'boss_yuanyi_viper', '稳定掉落', '猨翼印记', '猨翼印记', '印记', 1, 1, 1.0, false, '猨翼主Boss稳定掉落', 110),
            $this->drop('bossdrop_yuanyi_02', 'boss_yuanyi_viper', '概率掉落', '猨翼图纸碎片', '猨翼图纸碎片', '图纸', 1, 2, 0.22, false, '猨翼Boss图纸碎片', 120),
            $this->drop('bossdrop_yuanyi_03', 'boss_yuanyi_viper', '概率掉落', '毒瘴结晶', '毒瘴结晶', '材料', 1, 2, 0.20, false, '猨翼Boss结晶掉落', 130),
            $this->drop('bossdrop_yuanyi_04', 'boss_yuanyi_viper', '概率掉落', '蓝词条胚子装', '蓝词条胚子装', '蓝装', 1, 1, 0.12, false, '猨翼Boss蓝词条来源装', 140),
            $this->drop('bossdrop_yuanyi_05', 'boss_yuanyi_viper', '首通奖励', '猨翼首通奖励包', '猨翼首通奖励包', '礼包道具', 1, 1, 1.0, true, '猨翼Boss首通奖励', 150),

            $this->drop('bossdrop_lushu_01', 'boss_niuyang_lushu', '稳定掉落', '杻阳印记', '杻阳印记', '印记', 1, 1, 1.0, false, '杻阳主Boss稳定掉落', 160),
            $this->drop('bossdrop_lushu_02', 'boss_niuyang_lushu', '概率掉落', '鹿蜀王图纸', '鹿蜀王图纸', '图纸', 1, 1, 0.12, false, '鹿蜀王完整图纸', 170),
            $this->drop('bossdrop_lushu_03', 'boss_niuyang_lushu', '概率掉落', '鹿蜀纹角', '鹿蜀纹角', '材料', 1, 2, 0.20, false, '鹿蜀主材料', 180),
            $this->drop('bossdrop_lushu_04', 'boss_niuyang_lushu', '极低概率掉落', '40级蓝装', '40级蓝装', '蓝装', 1, 1, 0.06, false, '鹿蜀王高阶蓝装', 190),
            $this->drop('bossdrop_lushu_05', 'boss_niuyang_lushu', '首通奖励', '杻阳首通奖励包', '杻阳首通奖励包', '礼包道具', 1, 1, 1.0, true, '杻阳Boss首通奖励', 200),

            $this->drop('bossdrop_xuangui_01', 'boss_niuyang_xuangui', '稳定掉落', '旋龟印', '旋龟印', '印记', 1, 1, 1.0, false, '旋龟副Boss稳定掉落', 210),
            $this->drop('bossdrop_xuangui_02', 'boss_niuyang_xuangui', '概率掉落', '旋龟甲片', '旋龟甲片', '材料', 1, 2, 0.26, false, '旋龟副Boss材料', 220),
            $this->drop('bossdrop_xuangui_03', 'boss_niuyang_xuangui', '极低概率掉落', '旋龟核心', '旋龟核心', '核心', 1, 1, 0.08, false, '旋龟核心稀有掉落', 230),
            $this->drop('bossdrop_xuangui_04', 'boss_niuyang_xuangui', '首通奖励', '旋龟守脉礼', '旋龟守脉礼', '礼包道具', 1, 1, 1.0, true, '旋龟副Boss首通奖励', 240),

            $this->drop('bossdrop_dishan_01', 'boss_dishan_lu', '稳定掉落', '柢山印记', '柢山印记', '印记', 1, 1, 1.0, false, '柢山主Boss稳定掉落', 250),
            $this->drop('bossdrop_dishan_02', 'boss_dishan_lu', '概率掉落', '鯥王核心', '鯥王核心', '核心', 1, 1, 0.12, false, '鯥王核心', 260),
            $this->drop('bossdrop_dishan_03', 'boss_dishan_lu', '概率掉落', '护身符兑换材料', '护身符兑换材料', '材料', 1, 2, 0.18, false, '护身符兑换来源', 270),
            $this->drop('bossdrop_dishan_04', 'boss_dishan_lu', '概率掉落', '技能宝石碎片', '技能宝石碎片', '宝石', 1, 3, 0.20, false, '技能宝石碎片', 280),
            $this->drop('bossdrop_dishan_05', 'boss_dishan_lu', '首通奖励', '柢山首通奖励包', '柢山首通奖励包', '礼包道具', 1, 1, 1.0, true, '柢山Boss首通奖励', 290),

            $this->drop('bossdrop_tanyuan_01', 'boss_tanyuan_lei', '稳定掉落', '亶爰禁印', '亶爰禁印', '印记', 1, 1, 1.0, false, '亶爰主Boss稳定掉落', 300),
            $this->drop('bossdrop_tanyuan_02', 'boss_tanyuan_lei', '概率掉落', '类兽长髦', '类兽长髦', '材料', 1, 2, 0.22, false, '类兽主材料', 310),
            $this->drop('bossdrop_tanyuan_03', 'boss_tanyuan_lei', '概率掉落', '禁录残页', '禁录残页', '材料', 1, 2, 0.18, false, '功能区专属材料', 320),
            $this->drop('bossdrop_tanyuan_04', 'boss_tanyuan_lei', '首通奖励', '亶爰秘禁奖励包', '亶爰秘禁奖励包', '礼包道具', 1, 1, 1.0, true, '亶爰Boss首通奖励', 330),

            $this->drop('bossdrop_jishan_01', 'boss_jishan_boti', '稳定掉落', '基山秘印', '基山秘印', '印记', 1, 1, 1.0, false, '基山主Boss稳定掉落', 340),
            $this->drop('bossdrop_jishan_02', 'boss_jishan_boti', '概率掉落', '猼訑残角', '猼訑残角', '材料', 1, 2, 0.20, false, '猼訑材料', 350),
            $this->drop('bossdrop_jishan_03', 'boss_jishan_boti', '概率掉落', '灵晶秘匣', '灵晶秘匣', '礼包道具', 1, 1, 0.14, false, '灵晶秘藏奖励', 360),
            $this->drop('bossdrop_jishan_04', 'boss_jishan_boti', '极低概率掉落', '高阶属性宝石', '高阶属性宝石', '宝石', 1, 1, 0.05, false, '高阶宝石掉落', 370),
            $this->drop('bossdrop_jishan_05', 'boss_jishan_boti', '首通奖励', '基山秘藏奖励包', '基山秘藏奖励包', '礼包道具', 1, 1, 1.0, true, '基山Boss首通奖励', 380),

            $this->drop('bossdrop_ninetail_01', 'boss_qingqiu_nine_tail', '稳定掉落', '青丘印记', '青丘印记', '印记', 1, 1, 1.0, false, '青丘主Boss稳定掉落', 390),
            $this->drop('bossdrop_ninetail_02', 'boss_qingqiu_nine_tail', '概率掉落', '九尾妖影图纸', '九尾妖影图纸', '图纸', 1, 1, 0.10, false, '青丘终章图纸', 400),
            $this->drop('bossdrop_ninetail_03', 'boss_qingqiu_nine_tail', '概率掉落', '青丘核心', '青丘核心', '核心', 1, 1, 0.08, false, '青丘终章核心', 410),
            $this->drop('bossdrop_ninetail_04', 'boss_qingqiu_nine_tail', '概率掉落', '紫/金宝石碎片', '紫/金宝石碎片', '宝石', 1, 2, 0.18, false, '青丘高阶宝石碎片', 420),
            $this->drop('bossdrop_ninetail_05', 'boss_qingqiu_nine_tail', '极低概率掉落', '60级蓝装', '60级蓝装', '蓝装', 1, 1, 0.05, false, '高阶蓝装掉落', 430),
            $this->drop('bossdrop_ninetail_06', 'boss_qingqiu_nine_tail', '首通奖励', '青丘终章奖励包', '青丘终章奖励包', '礼包道具', 1, 1, 1.0, true, '青丘主Boss首通奖励', 440),

            $this->drop('bossdrop_guanguan_01', 'boss_qingqiu_guanguan', '稳定掉落', '灌灌印', '灌灌印', '印记', 1, 1, 1.0, false, '灌灌精英Boss稳定掉落', 450),
            $this->drop('bossdrop_guanguan_02', 'boss_qingqiu_guanguan', '概率掉落', '灌灌翎羽', '灌灌翎羽', '材料', 1, 2, 0.26, false, '灌灌材料掉落', 460),
            $this->drop('bossdrop_guanguan_03', 'boss_qingqiu_guanguan', '极低概率掉落', '灌灌羽晶', '灌灌羽晶', '宝石', 1, 1, 0.06, false, '灌灌羽晶掉落', 470),
            $this->drop('bossdrop_guanguan_04', 'boss_qingqiu_guanguan', '首通奖励', '灌灌首通奖励包', '灌灌首通奖励包', '礼包道具', 1, 1, 1.0, true, '灌灌首通奖励', 480),

            $this->drop('bossdrop_chiyu_01', 'boss_qingqiu_chiyu', '稳定掉落', '赤鱬印', '赤鱬印', '印记', 1, 1, 1.0, false, '赤鱬事件Boss稳定掉落', 490),
            $this->drop('bossdrop_chiyu_02', 'boss_qingqiu_chiyu', '概率掉落', '赤鱬珠', '赤鱬珠', '材料', 1, 2, 0.26, false, '赤鱬珠掉落', 500),
            $this->drop('bossdrop_chiyu_03', 'boss_qingqiu_chiyu', '极低概率掉落', '赤鱬内珠', '赤鱬内珠', '宝石', 1, 1, 0.05, false, '赤鱬内珠掉落', 510),
            $this->drop('bossdrop_chiyu_04', 'boss_qingqiu_chiyu', '首通奖励', '赤鱬首通奖励包', '赤鱬首通奖励包', '礼包道具', 1, 1, 1.0, true, '赤鱬首通奖励', 520),

            $this->drop('bossdrop_jiwei_01', 'boss_jiwei_seal', '稳定掉落', '箕尾印记', '箕尾印记', '印记', 1, 1, 1.0, false, '箕尾主Boss稳定掉落', 530),
            $this->drop('bossdrop_jiwei_02', 'boss_jiwei_seal', '概率掉落', '镇脉核心', '镇脉核心', '核心', 1, 1, 0.10, false, '箕尾镇脉核心', 540),
            $this->drop('bossdrop_jiwei_03', 'boss_jiwei_seal', '概率掉落', '60级主武器图纸', '60级主武器图纸', '图纸', 1, 1, 0.08, false, '终章主武图纸', 550),
            $this->drop('bossdrop_jiwei_04', 'boss_jiwei_seal', '概率掉落', '高阶技能宝石碎片', '高阶技能宝石碎片', '宝石', 1, 2, 0.16, false, '高阶技能宝石来源', 560),
            $this->drop('bossdrop_jiwei_05', 'boss_jiwei_seal', '极低概率掉落', '箕尾镇脉石', '箕尾镇脉石', '材料', 1, 1, 0.05, false, '终章关键材料', 570),
            $this->drop('bossdrop_jiwei_06', 'boss_jiwei_seal', '首通奖励', '箕尾封脉终礼', '箕尾封脉终礼', '礼包道具', 1, 1, 1.0, true, '箕尾Boss首通奖励', 580),
        ];

        foreach ($rows as $row) {
            StoryBossDrop::query()->updateOrCreate(
                ['boss_drop_id' => $row['boss_drop_id']],
                $row,
            );
        }
    }

    private function drop(
        string $bossDropId,
        string $bossId,
        string $dropType,
        string $itemId,
        string $itemName,
        string $itemType,
        int $countMin,
        int $countMax,
        float $probability,
        bool $firstClearOnly,
        string $useDesc,
        int $sortOrder,
    ): array {
        return [
            'boss_drop_id' => $bossDropId,
            'boss_id' => $bossId,
            'drop_type' => $dropType,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'item_type' => $itemType,
            'count_min' => $countMin,
            'count_max' => $countMax,
            'probability' => $probability,
            'first_clear_only' => $firstClearOnly,
            'use_desc' => $useDesc,
            'icon_path' => '',
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }
}
