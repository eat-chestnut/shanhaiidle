<?php

namespace App\Support;

use App\Models\BlueAffix;
use App\Models\EquipTemplate;
use App\Models\EquipmentSet;
use App\Models\Item;
use App\Models\SkillCatalog;
use App\Models\StoryBoss;
use App\Models\StoryChapter;
use App\Models\StoryMap;
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
            'orange' => '橙色',
        ];
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
            'craft' => '打造材料副本',
            'star' => '星材副本',
            'blueprint' => '图纸副本',
            'boss' => 'Boss材料副本',
            'gem' => '宝石副本',
            'refine' => '洗练材料副本',
        ];
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
        $query = Item::query()->where('is_enabled', true)->orderBy('sort_order')->orderBy('name');

        if ($scope !== null) {
            $query = $scope($query) ?? $query;
        }

        return $query->get()->mapWithKeys(fn (Item $item): array => [
            $item->id => sprintf('%s（%s）', $item->name, $item->id),
        ])->all();
    }

    public static function itemName(?string $itemId): string
    {
        if (blank($itemId)) {
            return '';
        }

        return (string) Item::query()->where('id', $itemId)->value('name');
    }

    public static function blueAffixOptions(?string $slotId = null): array
    {
        if (blank($slotId)) {
            return [];
        }

        $query = BlueAffix::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('affix_name');

        $query->whereJsonContains('slot_tags', $slotId);

        return $query->get()->mapWithKeys(fn (BlueAffix $affix): array => [
            $affix->affix_id => sprintf('%s（%s）', $affix->affix_name, $affix->affix_id),
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
            ->get()
            ->mapWithKeys(fn (EquipmentSet $set): array => [$set->id => sprintf('%s（%s）', $set->name, $set->id)])
            ->all();
    }

    public static function setLineOptions(): array
    {
        return EquipmentSet::query()
            ->where('is_enabled', true)
            ->orderBy('set_line_id')
            ->get()
            ->unique('set_line_id')
            ->mapWithKeys(fn (EquipmentSet $set): array => [$set->set_line_id => sprintf('%s（%s）', $set->name, $set->set_line_id)])
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
            $template->id => sprintf('%s（%s）', $template->name, $template->id),
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
                /** @var EquipTemplate|null $first */
                $first = $rows->first();
                $label = $first instanceof EquipTemplate
                    ? sprintf('%s（%s）', $first->name, $forgeFamilyId)
                    : $forgeFamilyId;

                return [$forgeFamilyId => $label];
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

    public static function optionLabel(array $options, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $options[$value] ?? $value;
    }
}
