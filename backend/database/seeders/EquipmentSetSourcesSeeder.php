<?php

namespace Database\Seeders;

use App\Models\EquipmentSetSource;
use Illuminate\Database\Seeder;

class EquipmentSetSourcesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->source('setsource_lushu_20', 'setline_lushu', '鹿蜀套', 'berserk', 20, '鹿蜀纹角', '赤金砂', ['堂庭印记', '猨翼印记', '白玉髓'], ['map_tangting', 'map_yuanyi'], ['boss_tangting_ape', 'boss_yuanyi_viper'], false, '无需独立图纸，依赖基础套材打造。', '20级鹿蜀套主要由堂庭与猨翼掉落的角纹、玉髓与印记合成。', 10),
            $this->source('setsource_xuangui_20', 'setline_xuangui', '旋龟套', 'guard', 20, '旋龟甲片', '水玉晶', ['堂庭印记', '猨翼骨片'], ['map_tangting', 'map_yuanyi'], ['boss_tangting_ape', 'boss_yuanyi_viper'], false, '无需独立图纸，依赖龟甲与水玉线。', '20级旋龟套强调守御，核心来自堂庭与猨翼的甲片与水玉。', 20),
            $this->source('setsource_guanguan_20', 'setline_guanguan', '灌灌套', 'hunt', 20, '灌灌翎羽', '迷榖枝', ['招摇印记', '猨翼印记'], ['map_zhaoyao', 'map_yuanyi'], ['boss_zhaoyao_xingxing', 'boss_yuanyi_viper'], false, '无需独立图纸。', '20级灌灌套起步于招摇与猨翼的风行材料。', 30),
            $this->source('setsource_migu_20', 'setline_migu', '迷榖套', 'evade', 20, '迷榖枝', '祝余叶', ['招摇印记', '桂木'], ['map_zhaoyao'], ['boss_zhaoyao_xingxing'], false, '无需独立图纸。', '20级迷榖套集中产自招摇之山。', 40),
            $this->source('setsource_chiyu_20', 'setline_chiyu', '赤鱬套', 'flame', 20, '赤鱬珠', '水玉晶', ['堂庭印记', '猨翼印记'], ['map_tangting', 'map_yuanyi'], ['boss_tangting_ape', 'boss_yuanyi_viper'], false, '无需独立图纸。', '20级赤鱬套由堂庭水玉与猨翼异气共同淬炼。', 50),
            $this->source('setsource_qingqiu_20', 'setline_qingqiu', '青丘套', 'frost', 20, '青丘封符', '迷榖枝', ['猨翼印记', '招摇印记'], ['map_yuanyi', 'map_zhaoyao'], ['boss_yuanyi_viper', 'boss_zhaoyao_xingxing'], false, '无需独立图纸。', '20级青丘套是终章系套装的前置版本。', 60),

            $this->source('setsource_lushu_40', 'setline_lushu', '鹿蜀套', 'berserk', 40, '鹿蜀纹角', '赤金砂', ['杻阳印记', '白金砂', '旋龟甲片'], ['map_niuyang'], ['boss_niuyang_lushu', 'boss_niuyang_xuangui'], true, '杻阳主线与副Boss均可产出鹿蜀套图纸素材。', '40级鹿蜀套完整成型于杻阳矿脉线，需要图纸与守矿Boss材料。', 70),
            $this->source('setsource_xuangui_40', 'setline_xuangui', '旋龟套', 'guard', 40, '旋龟甲片', '白金砂', ['杻阳印记', '深泽水纹碎片'], ['map_niuyang'], ['boss_niuyang_xuangui'], true, '旋龟副Boss线直接掉落套装图纸与核心材料。', '40级旋龟套主要由杻阳副Boss线承担来源。', 80),
            $this->source('setsource_guanguan_40', 'setline_guanguan', '灌灌套', 'hunt', 40, '灌灌翎羽', '灵晶碎片', ['杻阳印记', '基山秘印'], ['map_niuyang', 'map_jishan'], ['boss_niuyang_lushu', 'boss_jishan_boti'], true, '灌灌套40级图纸可由杻阳与基山双线获取。', '40级灌灌套跨杻阳与基山两条来源线。', 90),
            $this->source('setsource_migu_40', 'setline_migu', '迷榖套', 'evade', 40, '迷榖枝', '禁录残页', ['猨翼印记', '亶爰禁印'], ['map_yuanyi', 'map_tanyuan'], ['boss_yuanyi_viper', 'boss_tanyuan_lei'], true, '图纸由猨翼与亶爰秘禁共通掉落。', '40级迷榖套偏功能与支援，来源更偏秘禁线。', 100),
            $this->source('setsource_chiyu_40', 'setline_chiyu', '赤鱬套', 'flame', 40, '赤鱬珠', '裂渊石', ['柢山印记', '基山秘印'], ['map_dishan', 'map_jishan'], ['boss_dishan_lu', 'boss_jishan_boti'], true, '由柢山与基山高阶图纸材料合成。', '40级赤鱬套转入深层水系与秘藏路线。', 110),
            $this->source('setsource_qingqiu_40', 'setline_qingqiu', '青丘套', 'frost', 40, '青丘封符', '深层白玉髓', ['柢山印记', '青丘前置残印'], ['map_dishan', 'map_qingqiu'], ['boss_dishan_lu', 'boss_qingqiu_guanguan'], true, '青丘前置图纸主要由柢山与青丘外围获得。', '40级青丘套是终章前置套装线。', 120),

            $this->source('setsource_lushu_60', 'setline_lushu', '鹿蜀套', 'berserk', 60, '鹿蜀纹角', '箕尾镇脉石', ['青丘印记', '箕尾印记', '镇脉核心'], ['map_qingqiu', 'map_jiwei'], ['boss_qingqiu_nine_tail', 'boss_jiwei_seal'], true, '终章青丘与箕尾共同提供60级鹿蜀套图纸。', '60级鹿蜀套需要终章双图掉落与封脉材料。', 130),
            $this->source('setsource_xuangui_60', 'setline_xuangui', '旋龟套', 'guard', 60, '旋龟甲片', '箕尾镇脉石', ['柢山印记', '箕尾印记', '镇脉核心'], ['map_dishan', 'map_jiwei'], ['boss_dishan_lu', 'boss_jiwei_seal'], true, '终章旋龟套图纸由裂渊与箕尾线共同解锁。', '60级旋龟套是终章防御向套装。', 140),
            $this->source('setsource_guanguan_60', 'setline_guanguan', '灌灌套', 'hunt', 60, '灌灌翎羽', '青雘石', ['青丘印记', '青丘核心'], ['map_qingqiu'], ['boss_qingqiu_guanguan', 'boss_qingqiu_nine_tail'], true, '青丘终章主线可完整产出图纸与核心。', '60级灌灌套集中于青丘终章。', 150),
            $this->source('setsource_migu_60', 'setline_migu', '迷榖套', 'evade', 60, '迷榖枝', '箕尾镇脉石', ['青丘印记', '箕尾印记', '封脉残符'], ['map_qingqiu', 'map_jiwei'], ['boss_qingqiu_nine_tail', 'boss_jiwei_seal'], true, '青丘与箕尾共同掉落60级迷榖套图纸。', '60级迷榖套转向终章支援与续脉意象。', 160),
            $this->source('setsource_chiyu_60', 'setline_chiyu', '赤鱬套', 'flame', 60, '赤鱬珠', '青丘核心', ['青丘印记', '赤鱬印', '紫金宝石碎片'], ['map_qingqiu'], ['boss_qingqiu_chiyu', 'boss_qingqiu_nine_tail'], true, '青丘主线可完整获得赤鱬套60级素材。', '60级赤鱬套完全绑定青丘主线。', 170),
            $this->source('setsource_qingqiu_60', 'setline_qingqiu', '青丘套', 'frost', 60, '青丘封符', '箕尾镇脉石', ['青丘印记', '箕尾印记', '镇脉核心'], ['map_qingqiu', 'map_jiwei'], ['boss_qingqiu_nine_tail', 'boss_jiwei_seal'], true, '青丘与箕尾终章双图共同提供最终套装图纸。', '60级青丘套是南山一经终章代表套装。', 180),
        ];

        foreach ($rows as $row) {
            EquipmentSetSource::query()->updateOrCreate(
                ['set_source_id' => $row['set_source_id']],
                $row,
            );
        }
    }

    private function source(
        string $setSourceId,
        string $setLineId,
        string $setName,
        string $flowTag,
        int $setStage,
        string $mainMat1,
        string $mainMat2,
        array $subMaterials,
        array $sourceMaps,
        array $sourceBosses,
        bool $needBlueprint,
        string $blueprintSource,
        string $craftDesc,
        int $sortOrder,
    ): array {
        return [
            'set_source_id' => $setSourceId,
            'set_line_id' => $setLineId,
            'set_name' => $setName,
            'flow_tag' => $flowTag,
            'set_stage' => $setStage,
            'main_mat_1' => $mainMat1,
            'main_mat_2' => $mainMat2,
            'sub_materials' => $subMaterials,
            'source_maps' => $sourceMaps,
            'source_bosses' => $sourceBosses,
            'need_blueprint' => $needBlueprint,
            'blueprint_source' => $blueprintSource,
            'craft_desc' => $craftDesc,
            'icon_path' => '',
            'image_path' => '',
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }
}
