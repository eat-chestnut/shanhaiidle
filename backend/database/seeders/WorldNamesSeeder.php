<?php

namespace Database\Seeders;

use App\Models\WorldName;
use Illuminate\Database\Seeder;

class WorldNamesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->entry('world_shanhai', 'world', 'cosmology', '山海', '《山海经》与项目世界共同使用的总称', true, '世界观的总称，既指山川异域，也指被封存的远古秩序。', ['洪荒', '群山', '异兽'], ['章节文案', '系统总称'], 10),
            $this->entry('world_disorder', 'world', 'crisis', '山海失序', '项目原创世界观', false, '指封脉断裂后山川灵序倒流、异兽与禁地同时躁动的整体灾变。', ['灾变', '逆流', '失衡'], ['主线危机', '章节文案'], 20),
            $this->entry('world_fengmai', 'world', 'seal', '封脉', '项目原创世界观', false, '巡山司用于封镇山海异脉的古老脉络系统，也是主线反复提及的秩序核心。', ['封印', '灵脉', '禁制'], ['主线设定', '玩法说明'], 30),
            $this->entry('world_altar', 'world', 'ritual', '山海祭坛', '项目原创世界观', false, '巡山司与古司祭一脉用于续封脉、镇群厄的祭仪场所。', ['祭坛', '古纹', '神性'], ['剧情节点', '副本命名'], 40),
            $this->entry('world_qune', 'world', 'threat', '群厄', '项目原创世界观', false, '对失序后山海中成群涌现的异厄、妖祟与封脉裂象的统称。', ['灾厄', '妖祟', '失控'], ['剧情描述', '敌对势力'], 50),
            $this->entry('org_xunshansi', 'organization', 'faction', '巡山司', '项目原创世界观', false, '负责巡山、镇厄、续封脉的官方组织，也是玩家所属阵营。', ['官署', '古司', '巡守'], ['主角阵营', '功能入口'], 60),
            $this->entry('identity_xunshanshi', 'identity', 'title', '巡山使', '项目原创世界观', false, '正式执行巡山与镇厄之职的身份称谓。', ['巡守', '使者'], ['角色身份', '任务文案'], 70),
            $this->entry('identity_candidate', 'identity', 'title', '巡山使候补', '项目原创世界观', false, '玩家在南山一经开篇所处的身份阶段。', ['候补', '试炼'], ['开局身份', '新手引导'], 80),
            $this->entry('identity_zheneren', 'identity', 'title', '镇厄人', '项目原创世界观', false, '巡山司旧制下专司封厄与守印的执行者称呼。', ['守印', '镇厄'], ['剧情称谓'], 90),
            $this->entry('identity_hunter', 'identity', 'title', '山海猎者', '项目原创世界观', false, '活跃于诸山之间的独行者、采材者与妖兽猎人统称。', ['游猎', '山民'], ['支线角色', '世界观补充'], 100),
            $this->entry('identity_priest_desc', 'identity', 'bloodline', '司祭后裔', '项目原创世界观', false, '能识祭文、感山印、近神骨的一支古老血脉。', ['司祭', '血脉'], ['剧情身份', '设定扩展'], 110),
            $this->entry('identity_wushanwalker', 'identity', 'title', '巫山行者', '项目原创世界观', false, '游走于古祭路与山门残址间的神秘旅人称呼。', ['行脚', '巫纹'], ['支线设定'], 120),
            $this->entry('world_patrol_purge', 'world', 'duty', '巡山镇厄', '项目原创世界观', false, '巡山司行事总纲，指巡山、封脉、镇厄三位一体的职责。', ['巡守', '镇压'], ['玩法总目标', '章节目标'], 130),
            $this->entry('world_record', 'world', 'artifact', '巡厄录', '项目原创世界观', false, '记录山海异厄、封脉裂象与镇压过程的卷册，也是玩家行动的象征。', ['卷册', '记录'], ['系统名', '文案用词'], 140),
            $this->entry('world_seal_mark', 'world', 'artifact', '山印', '项目原创世界观', false, '封山镇脉时留下的印记，也是判断封脉状态的关键媒介。', ['印记', '古纹'], ['剧情道具', 'Boss掉落命名'], 150),
            $this->entry('world_divine_bone', 'world', 'relic', '神骨', '项目原创世界观', false, '上古遗存的神性骸骨，常与封脉、祭坛、山印共现。', ['骨纹', '遗骸'], ['剧情设定', '稀有材料命名'], 160),
            $this->entry('world_shed_skin', 'world', 'relic', '遗蜕', '项目原创世界观', false, '异兽、古神或封脉守影留下的壳与躯壳碎片。', ['残蜕', '壳片'], ['剧情设定', '掉落命名'], 170),
            $this->entry('world_resume_seal', 'world', 'ritual', '续封脉', '项目原创世界观', false, '将断裂封脉重新续接、短时恢复山域秩序的关键仪式。', ['续接', '封印'], ['主线目标', '终章设定'], 180),

            $this->entry('map_qushan', 'map', 'region', '鹊山', '《山海经·南山经》', true, '南山一经的起始山域，是巡山司重新启山后的第一站。', ['起始山域', '山门'], ['地图名', '序章文案'], 200),
            $this->entry('map_zhaoyao', 'map', 'region', '招摇之山', '《山海经·南山经》', true, '南山之首，多桂木与金玉，亦是狌狌聚群之地。', ['桂木', '金玉', '灵果'], ['主线地图', '章节名'], 210),
            $this->entry('map_tangting', 'map', 'region', '堂庭之山', '《山海经·南山经》', true, '棪木成林、水玉藏脉的山域，木脉与矿脉冲撞最为明显。', ['棪木', '水玉', '林谷'], ['主线地图', '掉落来源'], 220),
            $this->entry('map_yuanyi', 'map', 'region', '猨翼之山', '《山海经·南山经》', true, '白玉深藏、毒雾不散的险山，也是封脉裂痕最早显形之处。', ['毒雾', '白玉', '险崖'], ['主线地图', '章节名'], 230),
            $this->entry('map_niuyang', 'map', 'region', '杻阳之山', '《山海经·南山经》', true, '赤金白金并生的矿脉重地，鹿蜀与旋龟皆在此守脉。', ['矿脉', '鹿蜀', '旋龟'], ['主线地图', '套装来源'], 240),
            $this->entry('map_dishan', 'map', 'region', '柢山', '《山海经·南山经》', true, '多水无草木的荒冷山域，裂渊异种自此成群浮现。', ['裂渊', '寒水', '荒山'], ['主线地图', '终盘前置'], 250),
            $this->entry('map_tanyuan', 'map', 'region', '亶爰之山', '《山海经·南山经》', true, '不生草木、不容常行的禁山，是典型功能区与高危封禁地。', ['禁山', '古禁'], ['功能区地图'], 260),
            $this->entry('map_jishan', 'map', 'region', '基山', '《山海经·南山经》', true, '玉石与古灵碎片并存的秘藏山域，适合作为灵晶与宝石系统来源。', ['玉矿', '秘藏', '灵晶'], ['功能区地图', '宝石来源'], 270),
            $this->entry('map_qingqiu', 'map', 'region', '青丘之山', '《山海经·南山经》', true, '玉出其间、灌灌飞鸣、赤鱬潜游之地，也是终章妖雾汇聚之处。', ['青丘', '妖雾', '灵秀'], ['终章地图', '套装来源'], 280),
            $this->entry('map_jiwei', 'map', 'region', '箕尾之山', '项目原创终章扩展', false, '终章封印区，用于承接南山一经最后的镇脉与续封脉仪式。', ['封印区', '祭坛', '终章'], ['终章地图', '封印战场'], 290),

            $this->entry('boss_xingxing', 'boss', 'main', '狌狌', '《山海经·南山经》', true, '招摇山中白耳赤目之兽，项目中作为招摇主Boss兽群的原型。', ['猿形', '白耳', '迅捷'], ['Boss命名', '材料命名'], 300),
            $this->entry('boss_white_ape', 'boss', 'main', '白猿', '项目改写自堂庭异兽群', false, '堂庭之山中因灵泉浑浊而暴走的猿王称呼。', ['猿王', '重拳'], ['Boss命名', '章节文案'], 310),
            $this->entry('boss_lushu', 'boss', 'main', '鹿蜀', '《山海经·南山经》', true, '杻阳之山的名兽，被改写为守矿与守脉并行的王级异兽。', ['鹿身', '纹角', '奔行'], ['Boss命名', '套装线'], 320),
            $this->entry('boss_xuangui', 'boss', 'elite', '旋龟', '《山海经·南山经》', true, '兼具龟甲与旋流之意象的守脉异兽，用作副Boss与守矿线来源。', ['龟甲', '旋流', '重壳'], ['Boss命名', '材料命名'], 330),
            $this->entry('boss_lu', 'boss', 'main', '鯥', '《山海经·南山经》', true, '裂渊遗种，兼具深渊与水性异化特征，是柢山的主Boss原型。', ['水兽', '裂鳞', '深渊'], ['Boss命名', '终盘材料'], 340),
            $this->entry('boss_lei', 'boss', 'main', '类', '《山海经·南山经》', true, '亶爰秘禁中的古老守禁异种，被设定为功能区关底首领。', ['古兽', '秘禁'], ['Boss命名', '功能区剧情'], 350),
            $this->entry('boss_boti', 'boss', 'main', '猼訑', '《山海经·南山经》', true, '基山秘藏深处的守宝异兽，适合作为灵晶与宝石相关Boss。', ['异角', '守藏', '古兽'], ['Boss命名', '秘藏剧情'], 360),
            $this->entry('boss_guanguan', 'boss', 'elite', '灌灌', '《山海经·南山经》', true, '青丘一带成群出没的异鸟，在项目中承担精英Boss与套装线意象。', ['异鸟', '翎羽'], ['Boss命名', '套装线'], 370),
            $this->entry('boss_chiyu', 'boss', 'elite', '赤鱬', '《山海经·南山经》', true, '青丘水域深处的赤色异鱼，项目中承担事件Boss与珠类材料来源。', ['赤鱼', '内珠'], ['Boss命名', '材料命名'], 380),
            $this->entry('boss_ninetail', 'boss', 'main', '九尾狐', '《山海经·南山经》', true, '青丘主宰意象，作为终章主Boss与青丘套的核心来源。', ['九尾', '妖狐', '幻光'], ['Boss命名', '终章主Boss'], 390),

            $this->entry('material_guimu', 'material', 'flora', '桂木', '《山海经·南山经》', true, '招摇之山代表性草木资源，既可作地图资源名，也可作打造材料母题。', ['木材', '桂香'], ['地图资源', '材料命名'], 400),
            $this->entry('material_zhuyu', 'material', 'flora', '祝余', '《山海经·南山经》', true, '招摇山中安神定饥之草木，在项目中对应祝余叶等材料线。', ['草叶', '灵草'], ['地图资源', '材料命名'], 410),
            $this->entry('material_migu', 'material', 'flora', '迷榖', '《山海经·南山经》', true, '可指路定行的灵木，项目中延展为迷榖枝与迷榖套装线。', ['灵木', '风纹'], ['地图资源', '套装命名'], 420),
            $this->entry('material_yanmu', 'material', 'flora', '棪木', '《山海经·南山经》', true, '堂庭之山的代表性林木资源。', ['木材', '火纹'], ['地图资源', '材料命名'], 430),
            $this->entry('material_shuiyu', 'material', 'mineral', '水玉', '《山海经·南山经》', true, '堂庭之山相关玉脉名词，是水玉晶、水玉髓等材料的母题。', ['玉石', '水润'], ['材料命名', '地图资源'], 440),
            $this->entry('material_baiyu', 'material', 'mineral', '白玉', '《山海经·南山经》', true, '猨翼、柢山等地高频出现的玉类资源总称。', ['白玉', '矿脉'], ['材料命名', '套装来源'], 450),
            $this->entry('material_chijin', 'material', 'metal', '赤金', '《山海经·南山经》', true, '杻阳之山的金属资源母题。', ['赤金', '矿砂'], ['材料命名', '宝石命名'], 460),
            $this->entry('material_baijin', 'material', 'metal', '白金', '《山海经·南山经》', true, '与赤金并列的高价值矿材来源。', ['白金', '精金'], ['材料命名', '宝石命名'], 470),
            $this->entry('material_qinghuo', 'material', 'mineral', '青雘', '《山海经·南山经》', true, '青丘一带的青色灵矿母题，对应青雘石与青雘珠。', ['青矿', '灵石'], ['材料命名', '宝石命名'], 480),
            $this->entry('material_yingshui', 'material', 'water', '英水', '《山海经·南山经》', true, '南山灵水名词，适合作为英水灵珠、英水印的命名来源。', ['灵水', '水脉'], ['宝石命名', '世界观名词'], 490),
            $this->entry('material_yupei', 'material', 'relic', '育沛', '《山海经·南山经》', true, '可延展为育沛珠等高阶灵性素材。', ['灵珠', '玉佩'], ['宝石命名', '剧情道具'], 500),

            $this->entry('set_lushu', 'set', 'set_line', '鹿蜀套', '项目原创套装线', false, '以鹿蜀的奔行与矿脉守护意象构成的套装线。', ['赤纹', '兽角'], ['套装线', '装备命名'], 510),
            $this->entry('set_xuangui', 'set', 'set_line', '旋龟套', '项目原创套装线', false, '以旋龟甲壳、守脉、防御为核心意象的套装线。', ['龟甲', '水纹'], ['套装线', '装备命名'], 520),
            $this->entry('set_guanguan', 'set', 'set_line', '灌灌套', '项目原创套装线', false, '以灌灌异鸟、风翎、速度与灵动为核心意象。', ['羽翎', '青光'], ['套装线', '装备命名'], 530),
            $this->entry('set_migu', 'set', 'set_line', '迷榖套', '项目原创套装线', false, '以迷榖引路、风行、感知为核心意象的套装线。', ['木纹', '风晶'], ['套装线', '装备命名'], 540),
            $this->entry('set_chiyu', 'set', 'set_line', '赤鱬套', '项目原创套装线', false, '以赤鱬内珠、水火并济和术法波动为核心意象。', ['赤珠', '水火'], ['套装线', '装备命名'], 550),
            $this->entry('set_qingqiu', 'set', 'set_line', '青丘套', '项目原创套装线', false, '终章级别套装线，以青丘妖影、狐火和封尾意象构成。', ['狐纹', '妖火'], ['套装线', '装备命名'], 560),

            $this->entry('gem_chijinshi', 'gem', 'attr', '赤金石', '项目宝石命名', false, '偏向攻击向的属性宝石命名。', ['红金', '攻击'], ['宝石系统'], 570),
            $this->entry('gem_baiyujing', 'gem', 'attr', '白玉晶', '项目宝石命名', false, '偏向生命或防御向的属性宝石命名。', ['白玉', '守御'], ['宝石系统'], 580),
            $this->entry('gem_qinghuozhu', 'gem', 'attr', '青雘珠', '项目宝石命名', false, '偏向法术与灵性属性的宝石命名。', ['青矿', '术法'], ['宝石系统'], 590),
            $this->entry('gem_lushuwen', 'gem', 'skill', '鹿蜀纹石', '项目宝石命名', false, '以鹿蜀纹角意象延展出的技能宝石。', ['纹角', '奔袭'], ['宝石系统'], 600),
            $this->entry('gem_xingxingxue', 'gem', 'skill', '狌狌血玉', '项目宝石命名', false, '招摇Boss线技能宝石，适合表现狂暴与速度。', ['血玉', '狂暴'], ['宝石系统', 'Boss掉落'], 610),
            $this->entry('gem_migufeng', 'gem', 'skill', '迷榖风晶', '项目宝石命名', false, '迷榖套线常见技能宝石母题。', ['风晶', '引路'], ['宝石系统'], 620),
            $this->entry('gem_zhuyuling', 'gem', 'skill', '祝余灵晶', '项目宝石命名', false, '祝余相关技能宝石命名。', ['灵草', '回灵'], ['宝石系统'], 630),
            $this->entry('gem_yupeizhu', 'gem', 'skill', '育沛珠', '项目宝石命名', false, '偏向稀有灵珠类的技能宝石。', ['灵珠', '高阶'], ['宝石系统', 'Boss掉落'], 640),
            $this->entry('gem_yingshui', 'gem', 'skill', '英水灵珠', '项目宝石命名', false, '英水系技能宝石名。', ['灵水', '波纹'], ['宝石系统'], 650),
            $this->entry('gem_chiyu_neizhu', 'gem', 'skill', '赤鱬内珠', '项目宝石命名', false, '赤鱬线稀有珠类宝石。', ['赤珠', '水纹'], ['宝石系统', 'Boss掉落'], 660),
            $this->entry('gem_xuangui_jia', 'gem', 'skill', '旋龟甲石', '项目宝石命名', false, '防御与减伤向技能宝石命名。', ['甲壳', '守御'], ['宝石系统'], 670),
            $this->entry('gem_baijinsui', 'gem', 'attr', '白金髓', '项目宝石命名', false, '高阶属性宝石母题。', ['金髓', '高阶'], ['宝石系统'], 680),
            $this->entry('gem_guanguan_yu', 'gem', 'skill', '灌灌羽晶', '项目宝石命名', false, '灌灌线速度或术法强化向技能宝石。', ['羽晶', '迅捷'], ['宝石系统'], 690),
            $this->entry('gem_mark_xingxing', 'gem', 'seal', '狌狌印', '项目命名扩展', false, '用于衍生招摇Boss线印类掉落。', ['印记', '招摇'], ['Boss掉落', '宝石命名'], 700),
            $this->entry('gem_mark_lushu', 'gem', 'seal', '鹿蜀印', '项目命名扩展', false, '鹿蜀线印记命名。', ['印记', '杻阳'], ['Boss掉落'], 710),
            $this->entry('gem_mark_xuangui', 'gem', 'seal', '旋龟印', '项目命名扩展', false, '旋龟线印记命名。', ['印记', '守脉'], ['Boss掉落'], 720),
            $this->entry('gem_mark_guanguan', 'gem', 'seal', '灌灌印', '项目命名扩展', false, '灌灌线印记命名。', ['印记', '青丘'], ['Boss掉落'], 730),
            $this->entry('gem_mark_migu', 'gem', 'seal', '迷榖印', '项目命名扩展', false, '迷榖线印记命名。', ['印记', '引路'], ['Boss掉落'], 740),
            $this->entry('gem_mark_chiyu', 'gem', 'seal', '赤鱬印', '项目命名扩展', false, '赤鱬线印记命名。', ['印记', '赤珠'], ['Boss掉落'], 750),
            $this->entry('gem_mark_qingqiu', 'gem', 'seal', '青丘印', '项目命名扩展', false, '青丘终章线印记命名。', ['印记', '终章'], ['Boss掉落'], 760),
            $this->entry('gem_mark_yingshui', 'gem', 'seal', '英水印', '项目命名扩展', false, '与英水流域相关的印类命名。', ['印记', '灵水'], ['Boss掉落'], 770),
            $this->entry('gem_mark_jiwei', 'gem', 'seal', '箕尾镇印', '项目命名扩展', false, '箕尾终章封印区专属印记命名。', ['镇印', '封脉'], ['终章掉落'], 780),

            $this->entry('material_guizhi', 'material', 'craft', '桂枝', '项目材料命名', false, '初期打造与剧情常见基础材料。', ['木枝', '基础'], ['材料系统', '地图掉落'], 790),
            $this->entry('material_miguzhi', 'material', 'craft', '迷榖枝', '项目材料命名', false, '迷榖资源进一步加工后的基础材料。', ['木枝', '风纹'], ['材料系统', '地图掉落'], 800),
            $this->entry('material_zhuyuye', 'material', 'craft', '祝余叶', '项目材料命名', false, '祝余系采集材料。', ['草叶', '灵草'], ['材料系统', '地图掉落'], 810),
            $this->entry('material_chijinsha', 'material', 'craft', '赤金砂', '项目材料命名', false, '杻阳矿系常见冶炼前材料。', ['矿砂', '赤金'], ['材料系统', '地图掉落'], 820),
            $this->entry('material_baijinsha', 'material', 'craft', '白金砂', '项目材料命名', false, '杻阳矿系常见贵金属砂。', ['矿砂', '白金'], ['材料系统', '地图掉落'], 830),
            $this->entry('material_baiyusui', 'material', 'craft', '白玉髓', '项目材料命名', false, '猨翼与裂渊高频产出的玉髓材料。', ['玉髓', '白玉'], ['材料系统', '地图掉落'], 840),
            $this->entry('material_shuiyujing', 'material', 'craft', '水玉晶', '项目材料命名', false, '堂庭水玉脉产物。', ['水晶', '水玉'], ['材料系统', '地图掉落'], 850),
            $this->entry('material_qinghuoshi', 'material', 'craft', '青雘石', '项目材料命名', false, '青丘区域特有矿石。', ['青矿', '灵石'], ['材料系统', '地图掉落'], 860),
            $this->entry('material_lushuwenjiao', 'material', 'boss', '鹿蜀纹角', '项目材料命名', false, '鹿蜀王系Boss线代表性掉落。', ['角片', '纹路'], ['Boss材料'], 870),
            $this->entry('material_xuangui_jiapian', 'material', 'boss', '旋龟甲片', '项目材料命名', false, '旋龟线材料，可用于防御向套装与升品。', ['甲片', '龟壳'], ['Boss材料'], 880),
            $this->entry('material_luyulin', 'material', 'boss', '鯥羽鳞', '项目材料命名', false, '裂渊水兽系材料。', ['鳞羽', '水兽'], ['Boss材料'], 890),
            $this->entry('material_lei_mao', 'material', 'boss', '类兽长髦', '项目材料命名', false, '亶爰之类掉落的毛发状高阶材料。', ['长髦', '古兽'], ['Boss材料'], 900),
            $this->entry('material_boti_jiao', 'material', 'boss', '猼訑残角', '项目材料命名', false, '基山秘藏Boss材料。', ['残角', '秘藏'], ['Boss材料'], 910),
            $this->entry('material_guanguan_yu', 'material', 'boss', '灌灌翎羽', '项目材料命名', false, '青丘线常见精英Boss材料。', ['翎羽', '异鸟'], ['Boss材料', '地图掉落'], 920),
            $this->entry('material_chiyu_zhu', 'material', 'boss', '赤鱬珠', '项目材料命名', false, '赤鱬线掉落的珠类核心材料。', ['珠', '赤鱼'], ['Boss材料', '地图掉落'], 930),
            $this->entry('material_ninetail_fur', 'material', 'boss', '九尾残绒', '项目材料命名', false, '终章Boss线稀有狐绒。', ['狐绒', '终章'], ['Boss材料'], 940),
            $this->entry('material_qushan_mark', 'material', 'seal', '鹊山残印', '项目材料命名', false, '序章线残留封印印记。', ['残印', '序章'], ['剧情道具', '材料系统'], 950),
            $this->entry('material_zhaoyao_bone', 'material', 'seal', '招摇祭骨', '项目材料命名', false, '招摇山线祭仪残骨。', ['祭骨', '招摇'], ['剧情道具', 'Boss材料'], 960),
            $this->entry('material_qingqiu_talisman', 'material', 'seal', '青丘封符', '项目材料命名', false, '青丘终章相关封印符材。', ['封符', '青丘'], ['剧情道具', 'Boss材料'], 970),
            $this->entry('material_jiwei_stone', 'material', 'seal', '箕尾镇脉石', '项目材料命名', false, '箕尾终章续封脉核心材料。', ['镇脉石', '终章'], ['剧情道具', '终章材料'], 980),
        ];

        foreach ($rows as $row) {
            WorldName::query()->updateOrCreate(
                ['name_id' => $row['name_id']],
                $row,
            );
        }
    }

    private function entry(
        string $nameId,
        string $category,
        string $subCategory,
        string $displayName,
        string $sourceText,
        bool $fromOriginal,
        string $namingNote,
        array $visualTags,
        array $systemUsage,
        int $sortOrder,
    ): array {
        return [
            'name_id' => $nameId,
            'category' => $category,
            'sub_category' => $subCategory,
            'display_name' => $displayName,
            'source_text' => $sourceText,
            'from_original' => $fromOriginal,
            'naming_note' => $namingNote,
            'visual_tags' => $visualTags,
            'system_usage' => $systemUsage,
            'icon_path' => '',
            'image_path' => '',
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }
}
