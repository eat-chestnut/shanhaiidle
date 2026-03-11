<?php

namespace Database\Seeders;

use App\Models\StoryBoss;
use Illuminate\Database\Seeder;

class StoryBossesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->boss('boss_zhaoyao_xingxing', '狌狌王·白耳行君', '《山海经》狌狌意象的项目改写主Boss', 'map_zhaoyao', 'chapter_nanshan_01', '主Boss', 10, 1200, '招摇林脉失序后第一个真正成王的异兽，聚群守印，是南山失序最直观的开端。', ['白耳', '猿形', '赤目'], ['突进', '召群', '狂暴'], '白耳行君立于桂木之上，身后是被撕裂的山印与躁动的狌狌群。它并非只是在守林，而像在等待谁来靠近。', '狌狌王陨落后，招摇林脉短暂平静，散乱的山印碎辉重新沉入林地。', 10),
            $this->boss('boss_tangting_ape', '堂庭白猿王', '堂庭山域主Boss', 'map_tangting', 'chapter_nanshan_02', '主Boss', 20, 2200, '被浑浊灵泉影响而暴走的山谷霸主，是木脉与矿泉失衡的化身。', ['白猿', '重拳', '山谷'], ['重击', '投掷', '震荡'], '白猿王从断裂矿崖跃下，棪木与碎石在它脚边一起崩落。它的怒意让整片谷底都在回响。', '随着白猿王倒下，堂庭矿泉重新开始流动，山谷里终于能听见水声而非怒吼。', 20),
            $this->boss('boss_yuanyi_viper', '猨翼蝮王', '猨翼之山毒脉主Boss', 'map_yuanyi', 'chapter_nanshan_03', '主Boss', 30, 3600, '盘踞裂脉源头的毒雾王者，借封脉裂痕扩大自身毒域。', ['巨蝮', '毒雾', '裂玉'], ['毒域', '缠绕', '远扑'], '蝮王在毒雾间盘成山形，白玉矿壁上的裂光像它的鳞。你所踏入的每一步都在它的毒域之内。', '毒雾开始下沉，裂脉裸露出真实样貌。蝮王守着的并不是猎场，而是更深层的封脉伤口。', 30),
            $this->boss('boss_niuyang_lushu', '鹿蜀王·赤尾谣兽', '杻阳矿脉主Boss', 'map_niuyang', 'chapter_nanshan_04', '主Boss', 40, 5600, '原本守矿定脉的名兽，如今却被翻涌矿潮逼成暴走的赤尾妖兽。', ['鹿身', '赤尾', '纹角'], ['冲锋', '践踏', '流血'], '鹿蜀王从翻涌矿潮中奔出，赤尾拖过地面时溅起赤金与白金的火星。它仍在守山，只是已不辨敌我。', '鹿蜀王倒下后，杻阳主矿脉终于停止暴走，但深泽处仍传来旋龟拍水的沉响。', 40),
            $this->boss('boss_niuyang_xuangui', '旋龟守脉者', '杻阳副Boss线', 'map_niuyang', 'chapter_nanshan_04', '副Boss', 40, 5200, '守在矿山深泽中的副Boss，象征杻阳仍残留的古老守脉意志。', ['龟甲', '深泽', '旋水'], ['护盾', '旋流', '减速'], '深泽水面猛然旋起，巨甲缓缓浮出。旋龟仍在守脉，只是已把一切靠近者视作敌人。', '深泽重新归于沉静，旋龟守脉者留下的甲纹与矿印成为后续打造的重要线索。', 45),
            $this->boss('boss_dishan_lu', '鯥王·裂渊遗种', '柢山裂渊主Boss', 'map_dishan', 'chapter_nanshan_05', '主Boss', 50, 8200, '裂渊深处的遗种之王，形态与水压一同异化，是底层秩序崩裂的直接体现。', ['裂鳞', '黑水', '深渊'], ['潮压', '吐息', '召群'], '黑水翻涌，鯥王带着裂渊回声一同浮现。它像是整片谷底在回应你的闯入。', '鯥王沉入裂渊后，谷底的异潮终于退去，只留下通向禁山与终章的空洞回响。', 50),
            $this->boss('boss_tanyuan_lei', '亶爰之类', '亶爰秘禁主Boss', 'map_tanyuan', 'chapter_nanshan_06', '主Boss', 52, 9000, '被古禁塑成躯壳的守禁异种，既是兽，也是被禁制驱动的“门”。', ['古禁', '石纹', '空目'], ['封禁', '压制', '禁锢'], '禁门深处传来低沉摩擦声，像石门在自行开启。“类”从阴影中走出，像一段禁制长出了躯体。', '古禁重新合拢，失落的山禁记录得以收回。亶爰再度变回了“不能轻入”的山。', 60),
            $this->boss('boss_jishan_boti', '猼訑', '基山秘藏主Boss', 'map_jishan', 'chapter_nanshan_07', '主Boss', 55, 9800, '守在秘藏最深处的古兽，专门看守不属于现世的灵晶与古灵碎片。', ['异角', '矿光', '古兽'], ['跳袭', '灵压', '矿爆'], '猼訑踏过灵晶光晕缓缓现身，它像是在审视你是否配得上带走基山的秘藏。', '秘藏的矿光逐渐平息，猼訑守着的灵晶终于落入巡山司手中。', 70),
            $this->boss('boss_qingqiu_nine_tail', '青丘九尾妖影', '青丘终章主Boss', 'map_qingqiu', 'chapter_nanshan_08', '主Boss', 60, 13800, '并非单一九尾狐，而是青丘整片失序妖雾与狐火意志汇成的妖影。', ['九尾', '狐火', '妖雾'], ['幻影', '魅惑', '狐火'], '狐火在青丘深处一尾一尾点亮，最终凝成九尾妖影。你面对的不是一只妖狐，而是整片青丘的失序化身。', '九尾妖影消散后，青丘主脉重新显形，妖雾不再遮住前往箕尾的山路。', 80),
            $this->boss('boss_qingqiu_guanguan', '灌灌', '青丘精英Boss', 'map_qingqiu', 'chapter_nanshan_08', '精英Boss', 58, 11200, '栖于青丘高枝与风口的异鸟领主，常成群扰乱青丘外围灵场。', ['异鸟', '翎羽', '青光'], ['高速', '风刃', '俯冲'], '灌灌振翼掀起山风，羽光像锋刃一样在青丘林梢掠过。', '灌灌坠落后，青丘外围山风终于不再混杂尖啸。', 85),
            $this->boss('boss_qingqiu_chiyu', '赤鱬', '青丘事件/精英Boss', 'map_qingqiu', 'chapter_nanshan_08', '事件Boss', 58, 11600, '潜游于青丘灵水中的异鱼，内珠与赤焰相伴，是珠类与术法素材的重要来源。', ['赤鱼', '内珠', '灵水'], ['水浪', '灼烧', '冲刺'], '青丘水脉忽然翻赤，赤鱬拖着火色水痕破浪而来。', '赤鱬沉回深水后，只留下水面缓慢扩散的珠光。', 90),
            $this->boss('boss_jiwei_seal', '箕尾封脉守影', '箕尾终章封印区主Boss', 'map_jiwei', 'chapter_nanshan_08', '主Boss', 60, 15800, '终章封印区中由旧印、祭坛和残留厄气共同塑成的守影，是续封脉前最后的阻碍。', ['守影', '镇印', '祭坛'], ['镇压', '影袭', '封纹'], '祭坛光纹一圈圈亮起，封脉守影从镇印中央站起，像整个箕尾都在拒绝被再次封合。', '守影破碎，箕尾镇印重归手中。南山一经终于迎来续封脉的最后时刻。', 100),
        ];

        foreach ($rows as $row) {
            StoryBoss::query()->updateOrCreate(
                ['boss_id' => $row['boss_id']],
                $row,
            );
        }
    }

    private function boss(
        string $bossId,
        string $bossName,
        string $sourceText,
        string $mapId,
        string $chapterId,
        string $bossType,
        int $recommendLevel,
        int $recommendPower,
        string $loreRole,
        array $visualTags,
        array $combatTags,
        string $introCopy,
        string $clearCopy,
        int $sortOrder,
    ): array {
        return [
            'boss_id' => $bossId,
            'boss_name' => $bossName,
            'source_text' => $sourceText,
            'map_id' => $mapId,
            'chapter_id' => $chapterId,
            'boss_type' => $bossType,
            'recommend_level' => $recommendLevel,
            'recommend_power' => $recommendPower,
            'lore_role' => $loreRole,
            'visual_tags' => $visualTags,
            'combat_tags' => $combatTags,
            'intro_copy' => $introCopy,
            'clear_copy' => $clearCopy,
            'icon_path' => '',
            'portrait_path' => '',
            'banner_path' => '',
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }
}
