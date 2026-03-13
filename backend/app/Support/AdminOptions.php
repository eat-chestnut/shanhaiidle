<?php

namespace App\Support;

use App\Models\BlueAffix;
use App\Models\DailyDungeon;
use App\Models\EquipTemplate;
use App\Models\EquipmentSet;
use App\Models\Item;
use App\Models\MainStageChapter;
use App\Models\Milestone;
use App\Models\Monster;
use App\Models\SkillCatalog;
use App\Models\Stage;
use App\Models\StoryBoss;
use App\Models\StoryChapter;
use App\Models\StoryMap;
use App\Services\BattleDefaultsService;
use Illuminate\Database\Eloquent\Builder;

class AdminOptions
{
    public static function slotOptions(): array
    {
        return [
            'main_weapon' => '主武器',
            'off_weapon' => '副武器',
            'armor' => '盔甲',
            'belt' => '腰带',
            'shoes' => '鞋子',
            'gloves' => '护手',
            'helm' => '头盔',
            'necklace' => '项链',
            'talisman' => '护身符',
            'ring' => '戒指',
            'bracelet' => '手镯',
        ];
    }

    public static function slotGroupOptions(): array
    {
        return [
            'weapon' => '武器组',
            'armor' => '防具组',
            'cloak' => '披风组',
            'accessory' => '饰品组',
        ];
    }

    public static function equipTypeOptions(): array
    {
        return [
            'set' => '套装主养成',
            'ring' => '戒指',
            'bracelet' => '手镯',
            'talisman' => '护身符',
        ];
    }

    public static function flowOptions(): array
    {
        return [
            'berserk' => '狂战（berserk）',
            'guard' => '防战（guard）',
            'hunt' => '暴击流（hunt）',
            'evade' => '闪避流（evade）',
            'flame' => '火法（flame）',
            'frost' => '冰法（frost）',
        ];
    }

    public static function statOptions(): array
    {
        return [
            'ATK' => '攻击（ATK）',
            'HP' => '生命（HP）',
            'DEF' => '防御（DEF）',
            'CRIT_RATE' => '暴击率（CRIT_RATE）',
            'CRIT_DMG' => '暴击伤害（CRIT_DMG）',
            'CRIT_PERCENT' => '暴击率（CRIT_PERCENT）',
            'FINAL_DAMAGE' => '最终伤害（FINAL_DAMAGE）',
            'FINAL_REDUCTION' => '最终减伤（FINAL_REDUCTION）',
            'MELEE_ATK' => '近战攻击（MELEE_ATK）',
            'RANGED_ATK' => '远程攻击（RANGED_ATK）',
            'SPELL_ATK' => '术法攻击（SPELL_ATK）',
            'PDEF' => '物理防御（PDEF）',
            'MDEF' => '法术防御（MDEF）',
            'QI' => '气（QI）',
            'WD' => '物伤（WD）',
            'SP' => '术伤（SP）',
            'DODGE' => '闪避（DODGE）',
            'ATK_SPEED' => '攻击速度（ATK_SPEED）',
            'CDR' => '冷却缩减（CDR）',
            'LOOT_BONUS_PERCENT' => '掉落加成（LOOT_BONUS_PERCENT）',
        ];
    }

    public static function valueModeOptions(): array
    {
        return [
            'flat' => '固定值',
            'percent' => '百分比',
        ];
    }

    public static function rarityOptions(): array
    {
        return [
            'white' => '白色',
            'blue' => '蓝色',
            'purple' => '紫色',
            'gold' => '金色',
            'red' => '红色',
        ];
    }

    public static function qualityOptions(): array
    {
        return Item::QUALITY_OPTIONS;
    }

    public static function recipeTypeOptions(): array
    {
        return [
            'craft' => '打造',
            'exchange' => '兑换',
            'compose' => '合成',
            'rank_up' => '升阶',
        ];
    }

    public static function shopTabOptions(): array
    {
        return \App\Models\ShopGood::SHOP_TAB_OPTIONS;
    }

    public static function shopGoodsTypeOptions(): array
    {
        return \App\Models\ShopGood::GOODS_TYPE_OPTIONS;
    }

    public static function shopBuyLimitTypeOptions(): array
    {
        return \App\Models\ShopGood::BUY_LIMIT_TYPE_OPTIONS;
    }

    public static function milestoneConditionTypeOptions(): array
    {
        return Milestone::CONDITION_TYPE_OPTIONS;
    }

    public static function outputTypeOptions(): array
    {
        return [
            'equip' => '装备',
            'material' => '材料',
            'gem' => '宝石',
            'gift' => '礼包',
        ];
    }

    public static function itemTypeOptions(): array
    {
        return [
            'material' => '材料',
            'item' => '道具',
            'gem' => '宝石',
            'blueprint' => '图纸',
            'blueprint_fragment' => '图纸碎片',
            'currency' => '货币',
        ];
    }

    public static function itemMainTypeOptions(): array
    {
        return Item::MAIN_TYPE_OPTIONS;
    }

    public static function itemSubTypeOptionsByMainType(?string $mainType = null): array
    {
        if ($mainType !== null && array_key_exists($mainType, Item::SUB_TYPE_OPTIONS)) {
            return Item::SUB_TYPE_OPTIONS[$mainType];
        }

        return self::allItemSubTypeOptions();
    }

    public static function allItemSubTypeOptions(): array
    {
        $options = [];

        foreach (Item::SUB_TYPE_OPTIONS as $group) {
            $options += $group;
        }

        return $options;
    }

    public static function itemSubTypeLabelByMainType(?string $mainType, ?string $subType): string
    {
        return self::optionLabel(self::itemSubTypeOptionsByMainType($mainType), $subType);
    }

    public static function itemBindTypeOptions(): array
    {
        return Item::BIND_TYPE_OPTIONS;
    }

    public static function itemUseTypeOptions(): array
    {
        return Item::USE_TYPE_OPTIONS;
    }

    public static function itemSourceLibraryOptions(): array
    {
        return [
            'core_catalog' => '核心目录',
            'legacy_catalog_import' => '历史导入',
            'daily_dungeon_module' => '日常副本模块',
            'monster_rewards' => '怪物掉落',
            'stage_rewards' => '主线首通奖励',
            'shop_catalog' => '商城目录',
            'milestone_catalog' => '成长里程碑',
            'gift_catalog' => '礼包目录',
            'equipment_catalog' => '装备成品目录',
            'equipment_set_catalog' => '套装目录',
            'gem_catalog' => '宝石目录',
            'talisman_catalog' => '护符目录',
            'boss_core_catalog' => 'Boss核心目录',
        ];
    }

    public static function materialTypeOptions(): array
    {
        return [
            'craft' => '打造材料',
            'blueprint' => '图纸材料',
            'boss' => 'Boss材料',
            'star' => '升星材料',
            'refine' => '洗练材料',
            'gem' => '宝石材料',
            'currency' => '货币材料',
        ];
    }

    public static function itemMaterialTypeOptions(?string $type = null): array
    {
        $all = [
            'craft' => '打造材料',
            'boss' => 'Boss材料',
            'star' => '升星材料',
            'refine' => '洗练材料',
            'story' => '剧情材料',
            'token' => '令牌',
            'pack' => '奖励包',
            'dungeon_ticket' => '副本门票',
            'exchange_ticket' => '兑换凭证',
            'socket_tool' => '打孔道具',
            'salvage_tool' => '回收道具',
            'gem' => '宝石分类',
            'blueprint' => '图纸材料',
            'blueprint_fragment' => '图纸碎片材料',
            'currency' => '货币分类',
        ];

        return match ($type) {
            'material' => array_intersect_key($all, array_flip(['craft', 'boss', 'star', 'refine', 'story'])),
            'item' => array_intersect_key($all, array_flip(array_keys(self::consumableItemSubTypeOptions() + ['token' => '令牌']))),
            'gem' => ['gem' => $all['gem']],
            'blueprint' => ['blueprint' => $all['blueprint']],
            'blueprint_fragment' => ['blueprint_fragment' => $all['blueprint_fragment']],
            'currency' => ['currency' => $all['currency']],
            default => $all,
        };
    }

    public static function consumableItemSubTypeOptions(): array
    {
        return [
            'pack' => '奖励包',
            'dungeon_ticket' => '副本门票',
            'exchange_ticket' => '兑换凭证',
            'socket_tool' => '打孔道具',
            'salvage_tool' => '回收道具',
        ];
    }

    public static function itemSubTypeOptions(?string $type = null, ?string $materialType = null): array
    {
        $materialOptions = match ($materialType) {
            'boss' => [
                'boss_mark' => 'Boss印记',
                'boss_core' => 'Boss核心',
            ],
            'star' => [
                'star' => '升星材料',
            ],
            'refine' => [
                'refine' => '洗练材料',
            ],
            'story' => [
                'story_mat' => '剧情材料',
            ],
            default => [
                'forge_base' => '基础锻材',
                'forge_part' => '部位锻材',
                'forge_theme' => '主题锻材',
                'material' => '通用材料',
            ],
        };

        return match ($type) {
            'material' => $materialOptions,
            'item' => self::consumableItemSubTypeOptions() + ['token' => '令牌'],
            'gem' => self::gemTypeOptions(),
            'blueprint' => ['equipment_blueprint' => '装备图纸'],
            'blueprint_fragment' => ['theme_blueprint_fragment' => '主题图纸碎片'],
            'currency' => ['currency' => '货币'],
            default => [
                'forge_base' => '基础锻材',
                'forge_part' => '部位锻材',
                'forge_theme' => '主题锻材',
                'material' => '通用材料',
                'boss_mark' => 'Boss印记',
                'boss_core' => 'Boss核心',
                'star' => '升星材料',
                'refine' => '洗练材料',
                'story_mat' => '剧情材料',
                'pack' => '奖励包',
                'dungeon_ticket' => '副本门票',
                'exchange_ticket' => '兑换凭证',
                'socket_tool' => '打孔道具',
                'salvage_tool' => '回收道具',
                'token' => '令牌',
                'attr' => '属性宝石',
                'skill' => '技能宝石',
                'equipment_blueprint' => '装备图纸',
                'theme_blueprint_fragment' => '主题图纸碎片',
                'currency' => '货币',
            ],
        };
    }

    public static function defaultMaterialTypeForRecord(?string $type): ?string
    {
        return match ($type) {
            'material' => 'craft',
            'item' => 'pack',
            'gem' => 'gem',
            'blueprint' => 'blueprint',
            'blueprint_fragment' => 'blueprint_fragment',
            'currency' => 'currency',
            default => null,
        };
    }

    public static function defaultSubTypeForRecord(?string $type, ?string $materialType = null): ?string
    {
        $options = self::itemSubTypeOptions($type, $materialType);

        return array_key_first($options);
    }

    public static function normalizedMaterialTypeForRecord(?string $type, ?string $materialType, ?string $subType): ?string
    {
        return match ($type) {
            'material' => array_key_exists((string) $materialType, self::itemMaterialTypeOptions('material'))
                ? (string) $materialType
                : (string) self::defaultMaterialTypeForRecord('material'),
            'item' => in_array((string) $subType, array_keys(self::consumableItemSubTypeOptions() + ['token' => '令牌']), true)
                ? (string) $subType
                : (string) self::defaultMaterialTypeForRecord('item'),
            'gem' => 'gem',
            'blueprint' => 'blueprint',
            'blueprint_fragment' => 'blueprint_fragment',
            'currency' => 'currency',
            default => $materialType,
        };
    }

    public static function normalizedSubTypeForRecord(?string $type, ?string $materialType, ?string $subType): ?string
    {
        $options = self::itemSubTypeOptions($type, $materialType);
        $subType = (string) $subType;

        return array_key_exists($subType, $options) ? $subType : self::defaultSubTypeForRecord($type, $materialType);
    }

    public static function itemSubTypeLabel(?string $type, ?string $subType, ?string $materialType = null): string
    {
        return self::optionLabel(self::itemSubTypeOptions($type, $materialType), $subType);
    }

    public static function itemMaterialTypeLabel(?string $materialType): string
    {
        return self::optionLabel(self::itemMaterialTypeOptions(), $materialType);
    }

    public static function itemSourceTagOptions(): array
    {
        return [
            'Boss' => 'Boss',
            'Boss掉落' => 'Boss掉落',
            'Boss概率掉落' => 'Boss概率掉落',
            'Boss稳定掉落' => 'Boss稳定掉落',
            'boss_drop' => 'Boss掉落',
            'compose' => '合成来源',
            'dungeon_drop' => '副本掉落',
            '主线' => '主线',
            '主线普通掉落' => '主线普通掉落',
            '分解' => '分解',
            '副本' => '副本',
            '功能区' => '功能区',
            '图纸副本' => '图纸副本',
            '图纸合成' => '图纸合成',
            '宗门' => '宗门',
            '宝石副本' => '宝石副本',
            '旧版掉落' => '旧版掉落',
            '星材副本' => '星材副本',
            '洗练副本' => '洗练副本',
            '活动' => '活动',
            '礼包' => '礼包',
            '精英' => '精英',
            '终章' => '终章',
        ];
    }

    public static function itemUseTagOptions(): array
    {
        return [
            'craft' => '打造',
            'high_forge' => '高阶打造',
            'socket' => '镶嵌',
            '兑换' => '兑换',
            '剧情' => '剧情',
            '副本' => '副本',
            '升星' => '升星',
            '升阶' => '升阶',
            '商店' => '商店',
            '回收' => '回收',
            '图纸' => '图纸',
            '图纸合成' => '图纸合成',
            '奖励' => '奖励',
            '套装' => '套装',
            '宝石合成' => '宝石合成',
            '开启' => '开启',
            '成长' => '成长',
            '打孔' => '打孔',
            '打造' => '打造',
            '洗练' => '洗练',
            '礼包' => '礼包',
            '终章' => '终章',
            '终章打造' => '终章打造',
            '镶嵌' => '镶嵌',
            '高阶配方' => '高阶配方',
        ];
    }

    public static function optionLabels(array $options, array $values): array
    {
        return array_values(array_map(
            fn ($value): string => self::optionLabel($options, (string) $value),
            $values,
        ));
    }

    public static function gemTypeOptions(): array
    {
        return [
            'attr' => '属性宝石',
            'skill' => '技能宝石',
        ];
    }

    public static function gemEffectTypeOptions(): array
    {
        return [
            'stat' => '属性',
            'skill_modifier' => '技能修饰',
        ];
    }

    public static function gemTargetScopeOptions(): array
    {
        return [
            'global' => '全局',
            'sect' => '宗门',
            'flow' => '流派',
            'skill_id' => '指定技能',
        ];
    }

    public static function dungeonTypeOptions(): array
    {
        return [
            'star_sand' => '星砂副本',
            'spirit_jade' => '灵玉副本',
            'spirit_mark' => '灵印副本',
            'refine_soul' => '淬灵副本',
        ];
    }

    public static function stageOptions(): array
    {
        return Stage::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Stage $stage): array => [
                $stage->id => (string) $stage->name,
            ])
            ->all();
    }

    public static function dailyDungeonOptions(): array
    {
        return DailyDungeon::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->mapWithKeys(fn (DailyDungeon $dungeon): array => [
                $dungeon->dungeon_id => sprintf(
                    '%s｜%s',
                    (string) $dungeon->dungeon_id,
                    (string) $dungeon->display_name
                ),
            ])
            ->all();
    }

    public static function dailyDungeonName(?string $dungeonId): string
    {
        if (blank($dungeonId)) {
            return '';
        }

        return (string) DailyDungeon::query()
            ->where('dungeon_id', $dungeonId)
            ->value('display_name');
    }

    public static function worldNameCategoryOptions(): array
    {
        return [
            'world' => '世界',
            'organization' => '组织',
            'identity' => '身份',
            'map' => '地图',
            'boss' => 'Boss',
            'material' => '材料',
            'set' => '套装',
            'gem' => '宝石',
            'dungeon' => '副本',
        ];
    }

    public static function chapterRoleOptions(): array
    {
        return [
            '序章' => '序章',
            '主线' => '主线',
            '功能区' => '功能区',
            '终章' => '终章',
        ];
    }

    public static function mapTypeOptions(): array
    {
        return self::chapterRoleOptions();
    }

    public static function bossTypeOptions(): array
    {
        return [
            '主Boss' => '主Boss',
            '副Boss' => '副Boss',
            '精英Boss' => '精英Boss',
            '事件Boss' => '事件Boss',
        ];
    }

    public static function storyDropTierOptions(): array
    {
        return [
            '普通' => '普通',
            '精英' => '精英',
            'Boss稳定' => 'Boss稳定',
            'Boss概率' => 'Boss概率',
            'Boss极低概率' => 'Boss极低概率',
            '首通奖励' => '首通奖励',
        ];
    }

    public static function storyBossDropTypeOptions(): array
    {
        return [
            '稳定掉落' => '稳定掉落',
            '概率掉落' => '概率掉落',
            '极低概率掉落' => '极低概率掉落',
            '首通奖励' => '首通奖励',
        ];
    }

    public static function storyDropItemTypeOptions(): array
    {
        return [
            '材料' => '材料',
            '装备' => '装备',
            '蓝装' => '蓝装',
            '图纸' => '图纸',
            '宝石' => '宝石',
            '礼包道具' => '礼包道具',
            '货币' => '货币',
            '印记' => '印记',
            '核心' => '核心',
        ];
    }

    public static function setStageOptions(): array
    {
        return [
            20 => '20级',
            40 => '40级',
            50 => '50级',
            60 => '60级',
        ];
    }

    public static function themeOptions(): array
    {
        return [
            'nanshan' => '南山',
            'qingqiu' => '青丘',
            'kunlun' => '昆仑',
        ];
    }

    public static function booleanRadioOptions(): array
    {
        return [1 => '是', 0 => '否'];
    }

    public static function starterGiftRewardTypeOptions(): array
    {
        return [
            'fixed' => '固定奖励',
            'choice' => '自选奖励',
        ];
    }

    public static function recipeMaterialCategoryOptions(): array
    {
        return [
            'main' => '主材',
            'sub' => '辅材',
            'boss' => 'Boss材',
            'blueprint' => '图纸',
        ];
    }

    public static function itemOptions(?callable $scope = null): array
    {
        $query = Item::query()->where('is_enabled', true)->orderBy('sort_order')->orderBy('display_name');

        if ($scope !== null) {
            $query = $scope($query) ?? $query;
        }

        return $query->get()->mapWithKeys(fn (Item $item): array => [
            $item->item_id => (string) $item->display_name,
        ])->all();
    }

    public static function itemName(?string $itemId): string
    {
        if (blank($itemId)) {
            return '';
        }

        return (string) Item::query()->where('item_id', $itemId)->value('display_name');
    }

    public static function giftPackTypeOptions(): array
    {
        return \App\Models\GiftPack::PACK_TYPE_OPTIONS;
    }

    public static function giftPackModeOptions(): array
    {
        return \App\Models\GiftPack::PACK_MODE_OPTIONS;
    }

    public static function giftPackOpenModeOptions(): array
    {
        return \App\Models\GiftPack::OPEN_MODE_OPTIONS;
    }

    public static function giftPackContentModeOptions(): array
    {
        return \App\Models\GiftPackItem::CONTENT_MODE_OPTIONS;
    }

    public static function giftPackRecommendedSectOptions(): array
    {
        return \App\Models\GiftPackItem::RECOMMENDED_SECT_OPTIONS;
    }

    public static function giftPackCarrierItemOptions(): array
    {
        return self::itemOptions(fn (Builder $query): Builder => $query->where('main_type', 'gift_pack'));
    }

    public static function giftPackContentItemOptions(): array
    {
        return self::itemOptions(fn (Builder $query): Builder => $query->where('main_type', '!=', 'gift_pack'));
    }

    public static function shopRewardItemOptions(?string $goodsType = null): array
    {
        return match ($goodsType) {
            'direct_item' => self::giftPackContentItemOptions(),
            'gift_pack' => self::giftPackCarrierItemOptions(),
            default => self::itemOptions(),
        };
    }

    public static function shopPriceItemOptions(): array
    {
        return self::itemOptions(
            fn (Builder $query): Builder => $query
                ->where('main_type', 'currency')
                ->whereIn('item_id', ShopGoodsSupport::supportedPriceItemIds())
        );
    }

    public static function milestoneOptions(?string $exceptMilestoneId = null): array
    {
        return Milestone::query()
            ->when(
                filled($exceptMilestoneId),
                fn (Builder $query): Builder => $query->where('milestone_id', '!=', trim((string) $exceptMilestoneId))
            )
            ->orderBy('sort_order')
            ->orderBy('milestone_id')
            ->get()
            ->mapWithKeys(fn (Milestone $milestone): array => [
                (string) $milestone->milestone_id => sprintf(
                    '%s｜%s',
                    (string) $milestone->milestone_id,
                    (string) $milestone->display_name
                ),
            ])
            ->all();
    }

    public static function mainStageCombatChapterOptions(): array
    {
        return MainStageChapter::query()
            ->where('is_enabled', true)
            ->where('has_combat', true)
            ->orderBy('sort_order')
            ->orderBy('chapter_id')
            ->get()
            ->mapWithKeys(fn (MainStageChapter $chapter): array => [
                (string) $chapter->chapter_id => sprintf(
                    '%s｜%s',
                    (string) $chapter->chapter_id,
                    (string) $chapter->chapter_name
                ),
            ])
            ->all();
    }

    public static function monsterOptions(?string $kind = null, ?string $chapterId = null): array
    {
        return Monster::query()
            ->where('is_enabled', true)
            ->when($kind !== null, fn (Builder $query): Builder => $query->where('monster_type', $kind))
            ->when(filled($chapterId), fn (Builder $query): Builder => $query->where('chapter_id', $chapterId))
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->mapWithKeys(fn (Monster $monster): array => [
                $monster->monster_id => (string) $monster->display_name,
            ])
            ->all();
    }

    public static function monsterName(?string $monsterId): string
    {
        if (blank($monsterId)) {
            return '';
        }

        return (string) Monster::query()->where('monster_id', $monsterId)->value('display_name');
    }

    public static function starMaterialOptions(): array
    {
        return self::itemOptions(fn (Builder $query): Builder => $query
            ->where('type', 'material')
            ->where('material_type', 'star'));
    }

    public static function blueAffixOptions(?string $slotId = null, ?int $level = 0): array
    {
        if (blank($slotId) || $level === null || $level < 1) {
            return [];
        }

        $query = BlueAffix::query()
            ->where('is_enabled', true)
            ->where('unlock_level', '=', $level)
            ->orderBy('sort_order')
            ->orderBy('affix_name');

        $query->whereJsonContains('slot_tags', $slotId);

        return $query->get()->mapWithKeys(fn (BlueAffix $affix): array => [
            $affix->affix_id => sprintf('%s（%s）', $affix->affix_name, $affix->notes),
        ])->all();
    }

    public static function blueAffixName(?string $affixId): string
    {
        if (blank($affixId)) {
            return '';
        }

        return (string) BlueAffix::query()->where('affix_id', $affixId)->value('affix_name');
    }

    public static function skillOptions(): array
    {
        return SkillCatalog::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (SkillCatalog $skill): array => [$skill->id => sprintf('%s（%s）', $skill->name, $skill->id)])
            ->all();
    }

    public static function skillClassOptions(): array
    {
        $runtime = BattleDefaultsService::classOptions();
        if ($runtime !== []) {
            return $runtime;
        }

        return [
            'bing' => '兵宗',
            'vajra' => '金刚宗',
            'talisman' => '符箓宗',
            'global' => '通用',
        ];
    }

    public static function skillTypeOptions(): array
    {
        return [
            'active' => '主动技能',
            'passive' => '被动技能',
        ];
    }

    public static function skillTargetRuleOptions(): array
    {
        return [
            'primary' => '主目标',
            'self' => '自身',
            'cluster' => '范围聚类',
        ];
    }

    public static function skillTagOptions(): array
    {
        return [
            'single' => '单体',
            'aoe' => '范围',
            'armor_break' => '破甲',
            'barrier' => '结界',
            'boss' => 'Boss向',
            'burst' => '爆发',
            'chain' => '连锁',
            'cleanse' => '净化',
            'clear' => '清图',
            'control' => '控制',
            'control_resist' => '抗控',
            'counter' => '反击',
            'damage' => '伤害',
            'damage_down' => '减伤',
            'debuff' => '减益',
            'defense' => '防御',
            'delayed' => '延时',
            'dot' => '持续伤害',
            'gap_close' => '突进',
            'loot' => '掉落',
            'meta' => '成长',
            'resource' => '资源',
            'shield' => '护盾',
            'spell' => '术法',
            'splash' => '溅射',
            'stance' => '架势',
            'summon' => '召唤',
            'survival' => '生存',
            'sustain' => '续航',
            'ultimate' => '终结技',
        ];
    }

    public static function combatDamageSourceOptions(): array
    {
        return [
            'WD' => '物伤（WD）',
            'SP' => '术伤（SP）',
        ];
    }

    public static function sectOptions(): array
    {
        return SkillCatalog::query()
            ->where('is_enabled', true)
            ->whereNotNull('sect')
            ->where('sect', '!=', '')
            ->orderBy('sect')
            ->pluck('sect', 'sect')
            ->mapWithKeys(fn (string $sect, string $key): array => [$key => $sect])
            ->all();
    }

    public static function equipmentSetOptions(): array
    {
        return EquipmentSet::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('set_level')
            ->get()
            ->mapWithKeys(fn (EquipmentSet $set): array => [
                $set->id => sprintf(
                    '%s｜%s｜%s',
                    (string) $set->set_id,
                    (string) $set->display_name,
                    (string) ($set->set_level . '级')
                ),
            ])
            ->all();
    }

    public static function setLineOptions(): array
    {
        return EquipmentSet::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('set_level')
            ->get()
            ->unique('set_line_id')
            ->mapWithKeys(fn (EquipmentSet $set): array => [
                $set->set_line_id => static::displaySetLineName((string) $set->display_name, (string) $set->set_line_id),
            ])
            ->all();
    }

    public static function equipTemplateOptions(?callable $scope = null): array
    {
        $query = EquipTemplate::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($scope !== null) {
            $query = $scope($query) ?? $query;
        }

        return $query->get()->mapWithKeys(fn (EquipTemplate $template): array => [
            $template->id => (string) $template->name,
        ])->all();
    }

    public static function forgeSeriesOptions(): array
    {
        return EquipTemplate::query()
            ->where('is_enabled', true)
            ->whereNotNull('forge_family_id')
            ->where('forge_family_id', '!=', '')
            ->orderBy('forge_family_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('forge_family_id')
            ->mapWithKeys(function ($rows, string $forgeFamilyId): array {
                return [$forgeFamilyId => static::displayForgeSeriesName($forgeFamilyId, $rows)];
            })
            ->all();
    }

    public static function storyMapOptions(): array
    {
        return StoryMap::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (StoryMap $map): array => [$map->map_id => sprintf('%s（%s）', $map->map_name, $map->map_id)])
            ->all();
    }

    public static function storyBossOptions(): array
    {
        return StoryBoss::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (StoryBoss $boss): array => [$boss->boss_id => sprintf('%s（%s）', $boss->boss_name, $boss->boss_id)])
            ->all();
    }

    public static function storyChapterOptions(): array
    {
        return StoryChapter::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (StoryChapter $chapter): array => [$chapter->chapter_id => sprintf('%s（%s）', $chapter->chapter_name, $chapter->chapter_id)])
            ->all();
    }

    public static function mainStageChapterOptions(?string $exceptChapterId = null): array
    {
        return MainStageChapter::query()
            ->where('is_enabled', true)
            ->when(filled($exceptChapterId), fn ($query) => $query->where('chapter_id', '!=', $exceptChapterId))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (MainStageChapter $chapter): array => [$chapter->chapter_id => (string) $chapter->chapter_name])
            ->all();
    }

    public static function optionLabel(array $options, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $options[$value] ?? $value;
    }

    protected static function displaySetLineName(string $name, ?string $fallback = null): string
    {
        $label = trim((string) preg_replace('/[·・]\s*(20|40|60)级$/u', '', $name));

        return $label !== '' ? $label : (string) ($fallback ?? '');
    }

    protected static function displayForgeSeriesName(string $forgeFamilyId, $rows): string
    {
        /** @var EquipTemplate|null $first */
        $first = $rows
            ->sortBy(fn (EquipTemplate $row): int => $row->quality_tier === 'normal' ? 0 : 1)
            ->first();

        if ($first instanceof EquipTemplate) {
            $slotLabel = self::optionLabel(self::slotOptions(), (string) $first->slot);

            if (filled($first->set_line_id)) {
                $setLineName = self::displaySetLineName(
                    (string) EquipmentSet::query()
                        ->where('set_line_id', $first->set_line_id)
                        ->orderBy('stage')
                        ->value('name'),
                    (string) $first->set_line_id,
                );

                if ($setLineName !== '') {
                    return sprintf('%s·%s系列', $setLineName, $slotLabel);
                }
            }

            $flowLabel = self::displayBusinessLabel(self::optionLabel(self::flowOptions(), filled($first->flow_tag) ? (string) $first->flow_tag : null));
            if ($flowLabel !== '—' && $slotLabel !== '—') {
                return sprintf('%s·%s系列', $flowLabel, $slotLabel);
            }

            if ($slotLabel !== '—') {
                return sprintf('%s系列', $slotLabel);
            }
        }

        return $forgeFamilyId;
    }

    protected static function displayBusinessLabel(string $label): string
    {
        $cleaned = trim((string) preg_replace('/（[^）]*）/u', '', $label));

        return $cleaned !== '' ? $cleaned : $label;
    }
}
