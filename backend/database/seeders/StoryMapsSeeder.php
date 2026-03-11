<?php

namespace Database\Seeders;

use App\Models\StoryMap;
use Illuminate\Database\Seeder;

class StoryMapsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->map('map_quque_start', '鹊山将启', 1, '序章', '南山一经', '序章开山试炼区域。', 1, 5, ['序章', '山门', '残印'], '古旧山门半开，风里满是久未启封的灰尘与残留灵息。', 300, '角色1级解锁', 'chapter_nanshan_00', '', 'pool_quque_normal', 'pool_quque_elite', 'pool_quque_boss', 10),
            $this->map('map_zhaoyao', '招摇之山', 2, '主线', '南山一经', '南山之首，多桂木与金玉。', 1, 10, ['桂木', '祝余', '迷榖'], '林木繁茂却躁动不安，灵果与山印并生，兽群气息浮在树梢间。', 800, '完成序章后解锁', 'chapter_nanshan_01', 'boss_zhaoyao_xingxing', 'pool_zhaoyao_normal', 'pool_zhaoyao_elite', 'pool_zhaoyao_boss', 20),
            $this->map('map_tangting', '堂庭之山', 3, '主线', '南山一经', '棪木成林，水玉藏脉。', 10, 20, ['棪木', '水玉', '矿泉'], '木脉与水玉矿脉交织，整片山谷既丰饶又紧绷。', 1600, '角色10级且通关招摇之山', 'chapter_nanshan_02', 'boss_tangting_ape', 'pool_tangting_normal', 'pool_tangting_elite', 'pool_tangting_boss', 30),
            $this->map('map_yuanyi', '猨翼之山', 4, '主线', '南山一经', '毒雾终年不散，白玉深藏。', 20, 30, ['毒雾', '白玉', '裂痕'], '山壁白玉泛冷光，谷内毒雾压低视野，封脉裂痕从岩层深处发亮。', 2600, '角色20级且通关堂庭之山', 'chapter_nanshan_03', 'boss_yuanyi_viper', 'pool_yuanyi_normal', 'pool_yuanyi_elite', 'pool_yuanyi_boss', 40),
            $this->map('map_niuyang', '杻阳之山', 5, '主线', '南山一经', '赤金与白金并生的守脉矿山。', 30, 40, ['赤金', '白金', '鹿蜀', '旋龟'], '矿脉翻涌，山地与深泽互相牵制，奔兽与守龟都显露异化征兆。', 4200, '角色30级且通关猨翼之山', 'chapter_nanshan_04', 'boss_niuyang_lushu', 'pool_niuyang_normal', 'pool_niuyang_elite', 'pool_niuyang_boss', 50),
            $this->map('map_dishan', '柢山裂渊', 6, '主线', '南山一经', '荒冷裂谷与深层异种的聚集之地。', 40, 50, ['裂渊', '深水', '异种'], '谷底潮声沉闷，寒水自裂壁渗出，像一整片正在呼吸的深渊。', 6200, '角色40级且通关杻阳之山', 'chapter_nanshan_05', 'boss_dishan_lu', 'pool_dishan_normal', 'pool_dishan_elite', 'pool_dishan_boss', 60),
            $this->map('map_tanyuan', '亶爰秘禁', 7, '功能区', '南山一经', '被重新记录的禁山与古门。', 40, 55, ['禁门', '秘禁', '残录'], '山中无草木，古门半裂，山风像从密封多年的石室中吹出。', 7600, '角色40级且推进柢山裂渊后开放', 'chapter_nanshan_06', 'boss_tanyuan_lei', 'pool_tanyuan_normal', 'pool_tanyuan_elite', 'pool_tanyuan_boss', 70),
            $this->map('map_jishan', '基山秘藏', 8, '功能区', '南山一经', '灵晶、古兽与异矿交织的秘藏宝地。', 45, 58, ['灵晶', '玉矿', '秘藏'], '矿光在洞窟深处流动，像一条条尚未凝固的灵脉。', 9000, '角色45级且亶爰秘禁开放后解锁', 'chapter_nanshan_07', 'boss_jishan_boti', 'pool_jishan_normal', 'pool_jishan_elite', 'pool_jishan_boss', 80),
            $this->map('map_qingqiu', '青丘之山', 9, '终章', '南山一经', '妖雾与灵秀并存的终章山域。', 50, 60, ['青丘', '妖雾', '九尾', '灌灌'], '青光与妖雾共生，山色灵秀却处处透出异光，像整座山都在做梦。', 12800, '角色50级且完成基山秘藏后解锁', 'chapter_nanshan_08', 'boss_qingqiu_nine_tail', 'pool_qingqiu_normal', 'pool_qingqiu_elite', 'pool_qingqiu_boss', 90),
            $this->map('map_jiwei', '箕尾之山', 10, '终章', '南山一经', '终章封印区与续封脉仪式场。', 55, 60, ['箕尾', '封印', '祭坛'], '山风穿过残断祭柱，封印回响与镇印气息在山谷间叠成低鸣。', 15600, '角色55级且击破青丘之山后开放', 'chapter_nanshan_08', 'boss_jiwei_seal', 'pool_jiwei_normal', 'pool_jiwei_elite', 'pool_jiwei_boss', 100),
        ];

        foreach ($rows as $row) {
            StoryMap::query()->updateOrCreate(
                ['map_id' => $row['map_id']],
                $row,
            );
        }
    }

    private function map(
        string $mapId,
        string $mapName,
        int $mapOrder,
        string $mapType,
        string $volumeName,
        string $sourceText,
        int $levelMin,
        int $levelMax,
        array $themeTags,
        string $atmosphereDesc,
        int $recommendPower,
        string $unlockCondition,
        string $chapterId,
        string $bossId,
        string $normalDropPool,
        string $eliteDropPool,
        string $bossDropPool,
        int $sortOrder,
    ): array {
        return [
            'map_id' => $mapId,
            'map_name' => $mapName,
            'map_order' => $mapOrder,
            'map_type' => $mapType,
            'volume_name' => $volumeName,
            'source_text' => $sourceText,
            'level_min' => $levelMin,
            'level_max' => $levelMax,
            'theme_tags' => $themeTags,
            'atmosphere_desc' => $atmosphereDesc,
            'recommend_power' => $recommendPower,
            'unlock_condition' => $unlockCondition,
            'chapter_id' => $chapterId,
            'boss_id' => $bossId,
            'normal_drop_pool' => $normalDropPool,
            'elite_drop_pool' => $eliteDropPool,
            'boss_drop_pool' => $bossDropPool,
            'icon_path' => '',
            'banner_path' => '',
            'bg_path' => '',
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }
}
