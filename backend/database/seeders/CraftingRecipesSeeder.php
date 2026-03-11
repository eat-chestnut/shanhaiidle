<?php

namespace Database\Seeders;

use App\Models\CraftingRecipe;
use App\Models\EquipTemplate;
use App\Models\Item;
use Illuminate\Database\Seeder;

class CraftingRecipesSeeder extends Seeder
{
    private const SET_MAIN_MATS = [
        'setline_lushu' => '鹿蜀纹角',
        'setline_xuangui' => '旋龟甲片',
        'setline_guanguan' => '灌灌翎羽',
        'setline_migu' => '迷榖枝',
        'setline_chiyu' => '赤鱬珠',
        'setline_qingqiu' => '青丘封符',
    ];

    private const FLOW_MAIN_MATS = [
        'berserk' => '白玉髓',
        'guard' => '水玉晶',
        'hunt' => '赤金砂',
        'evade' => '迷榖枝',
        'flame' => '赤鱬珠',
        'frost' => '青雘石',
    ];

    private const STAGE_RULES = [
        20 => ['gold' => 3000, 'stage_mat' => '基础打造材料', 'stage_count' => 12, 'blueprint_gold' => 5000, 'fragment' => 'bp_fragment_nanshan'],
        40 => ['gold' => 12000, 'stage_mat' => '赤金砂', 'stage_count' => 16, 'blueprint_gold' => 12000, 'fragment' => 'bp_fragment_nanshan'],
        50 => ['gold' => 28000, 'stage_mat' => '裂渊石', 'stage_count' => 20, 'blueprint_gold' => 18000, 'fragment' => 'bp_fragment_qingqiu'],
        60 => ['gold' => 60000, 'stage_mat' => '箕尾镇脉石', 'stage_count' => 24, 'blueprint_gold' => 32000, 'fragment' => 'bp_fragment_qingqiu'],
    ];

    public function run(): void
    {
        $rows = [];
        $validIds = [];
        $sortOrder = 10;

        $templates = EquipTemplate::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            if ($template->slot === 'talisman') {
                if ($template->quality_tier !== 'normal') {
                    continue;
                }

                $rows[] = $this->buildTalismanExchangeRecipe($template, $sortOrder);
                $sortOrder += 10;
                continue;
            }

            if (! $template->forge_enabled) {
                continue;
            }

            if ($template->quality_tier !== 'normal') {
                continue;
            }

            $rows[] = $this->buildNormalCraftRecipe($template, $sortOrder);
            $sortOrder += 10;
        }

        $blueprintItems = Item::query()
            ->where('type', 'blueprint')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($blueprintItems as $item) {
            $rows[] = $this->buildBlueprintComposeRecipe($item, $sortOrder);
            $sortOrder += 10;
        }

        foreach ($this->gemComposeRecipes($sortOrder) as $recipe) {
            $rows[] = $recipe;
            $sortOrder += 10;
        }

        foreach ($rows as $row) {
            $validIds[] = $row['recipe_id'];
            CraftingRecipe::query()->updateOrCreate(
                ['recipe_id' => $row['recipe_id']],
                $row,
            );
        }

        CraftingRecipe::query()->whereNotIn('recipe_id', $validIds)->delete();
    }

    private function buildNormalCraftRecipe(EquipTemplate $template, int $sortOrder): array
    {
        $level = (int) $template->required_level;
        $rule = self::STAGE_RULES[$level] ?? self::STAGE_RULES[20];
        $group = (string) ($template->slot_group ?: 'armor');
        $mainCount = match ($group) {
            'weapon' => 10,
            'accessory' => 8,
            default => 9,
        };
        $mainCount += match ($level) {
            40 => 3,
            50 => 5,
            60 => 8,
            default => 0,
        };

        $primaryMat = filled($template->set_line_id)
            ? (self::SET_MAIN_MATS[$template->set_line_id] ?? '基础打造材料')
            : (self::FLOW_MAIN_MATS[(string) ($template->flow_tag ?? '')] ?? '白玉髓');

        $costItems = [
            ['item_id' => $primaryMat, 'count' => $mainCount],
            ['item_id' => $rule['stage_mat'], 'count' => $rule['stage_count']],
        ];

        if ($level >= 40) {
            $costItems[] = ['item_id' => $level >= 60 ? 'boss_mark_qingqiu' : 'boss_mark_nanshan', 'count' => $level >= 60 ? 5 : 3];
        }

        if (filled($template->blueprint_item_id)) {
            $costItems[] = ['item_id' => (string) $template->blueprint_item_id, 'count' => 1];
        }

        return [
            'recipe_id' => 'rcp_craft_' . $template->id,
            'recipe_type' => 'craft',
            'output_type' => 'equip',
            'output_id' => (string) $template->id,
            'output_count' => 1,
            'unlock_level' => $level,
            'cost_items' => $costItems,
            'cost_gold' => $rule['gold'],
            'cost_currency' => null,
            'notes' => sprintf('%d级%s打造配方', $level, $template->name),
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }

    private function buildTalismanExchangeRecipe(EquipTemplate $template, int $sortOrder): array
    {
        $level = (int) $template->required_level;

        return [
            'recipe_id' => 'rcp_exchange_' . $template->id,
            'recipe_type' => 'exchange',
            'output_type' => 'equip',
            'output_id' => (string) $template->id,
            'output_count' => 1,
            'unlock_level' => $level,
            'cost_items' => [
                ['item_id' => '护身符兑换凭证', 'count' => 1],
                ['item_id' => $level >= 60 ? '箕尾印记' : ($level >= 50 ? '柢山印记' : '堂庭印记'), 'count' => $level >= 60 ? 6 : 4],
                ['item_id' => $level >= 60 ? '镇脉核心' : '护身符兑换材料', 'count' => 1],
            ],
            'cost_gold' => match ($level) {
                40 => 18000,
                50 => 36000,
                default => 68000,
            },
            'cost_currency' => '宗门令',
            'notes' => sprintf('%d级护身符兑换配方', $level),
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }

    private function buildBlueprintComposeRecipe(Item $item, int $sortOrder): array
    {
        $theme = $this->themeForBlueprint((string) $item->id, (string) $item->name);
        $stage = $this->stageForBlueprint((string) $item->id, (string) $item->name);
        $rule = self::STAGE_RULES[$stage] ?? self::STAGE_RULES[20];

        return [
            'recipe_id' => 'rcp_compose_' . $item->id,
            'recipe_type' => 'compose',
            'output_type' => 'material',
            'output_id' => (string) $item->id,
            'output_count' => 1,
            'unlock_level' => $stage >= 40 ? 30 : 20,
            'cost_items' => [
                ['item_id' => $this->fragmentForTheme($theme), 'count' => 20],
            ],
            'cost_gold' => $rule['blueprint_gold'],
            'cost_currency' => null,
            'notes' => sprintf('%s图纸合成配方', $item->name),
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }

    private function gemComposeRecipes(int $sortOrder): array
    {
        return [
            [
                'recipe_id' => 'rcp_compose_attr_blue_random',
                'recipe_type' => 'compose',
                'output_type' => 'item',
                'output_id' => 'box_attr_gem_blue_random',
                'output_count' => 1,
                'unlock_level' => 40,
                'cost_items' => [['item_id' => '属性宝石碎片·白蓝', 'count' => 20]],
                'cost_gold' => 4000,
                'cost_currency' => null,
                'notes' => '蓝色属性宝石随机箱合成',
                'sort_order' => $sortOrder,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_compose_attr_purple_random',
                'recipe_type' => 'compose',
                'output_type' => 'item',
                'output_id' => 'box_attr_gem_purple_random',
                'output_count' => 1,
                'unlock_level' => 50,
                'cost_items' => [['item_id' => '紫/金宝石碎片', 'count' => 20]],
                'cost_gold' => 12000,
                'cost_currency' => null,
                'notes' => '紫色属性宝石随机箱合成',
                'sort_order' => $sortOrder + 10,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_compose_attr_gold_random',
                'recipe_type' => 'compose',
                'output_type' => 'item',
                'output_id' => 'box_attr_gem_gold_random',
                'output_count' => 1,
                'unlock_level' => 60,
                'cost_items' => [
                    ['item_id' => '紫/金宝石碎片', 'count' => 40],
                    ['item_id' => '青丘核心', 'count' => 1],
                ],
                'cost_gold' => 26000,
                'cost_currency' => null,
                'notes' => '金色属性宝石随机箱合成',
                'sort_order' => $sortOrder + 20,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_compose_skill_blue_random',
                'recipe_type' => 'compose',
                'output_type' => 'item',
                'output_id' => 'box_skill_gem_blue_random',
                'output_count' => 1,
                'unlock_level' => 40,
                'cost_items' => [['item_id' => '技能宝石碎片', 'count' => 20]],
                'cost_gold' => 5000,
                'cost_currency' => null,
                'notes' => '蓝色技能宝石随机箱合成',
                'sort_order' => $sortOrder + 30,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_compose_skill_purple_random',
                'recipe_type' => 'compose',
                'output_type' => 'item',
                'output_id' => 'box_skill_gem_purple_random',
                'output_count' => 1,
                'unlock_level' => 50,
                'cost_items' => [
                    ['item_id' => '技能宝石碎片', 'count' => 30],
                    ['item_id' => 'refine_core_advanced', 'count' => 2],
                ],
                'cost_gold' => 14000,
                'cost_currency' => null,
                'notes' => '紫色技能宝石随机箱合成',
                'sort_order' => $sortOrder + 40,
                'is_enabled' => true,
            ],
            [
                'recipe_id' => 'rcp_compose_skill_gold_random',
                'recipe_type' => 'compose',
                'output_type' => 'item',
                'output_id' => 'box_skill_gem_gold_random',
                'output_count' => 1,
                'unlock_level' => 60,
                'cost_items' => [
                    ['item_id' => '高阶技能宝石碎片', 'count' => 20],
                    ['item_id' => '镇脉核心', 'count' => 1],
                ],
                'cost_gold' => 28000,
                'cost_currency' => null,
                'notes' => '金色技能宝石随机箱合成',
                'sort_order' => $sortOrder + 50,
                'is_enabled' => true,
            ],
        ];
    }

    private function themeForBlueprint(string $id, string $name): string
    {
        $value = $id . '|' . $name;
        if (str_contains($value, 'qingqiu') || str_contains($value, '青丘')) {
            return 'qingqiu';
        }
        if (str_contains($value, 'kunlun') || str_contains($value, '昆仑')) {
            return 'kunlun';
        }

        return 'nanshan';
    }

    private function stageForBlueprint(string $id, string $name): int
    {
        $value = $id . '|' . $name;
        if (str_contains($value, '60')) {
            return 60;
        }
        if (str_contains($value, '50')) {
            return 50;
        }
        if (str_contains($value, '40')) {
            return 40;
        }

        return 20;
    }

    private function fragmentForTheme(string $theme): string
    {
        return match ($theme) {
            'qingqiu' => 'bp_fragment_qingqiu',
            'kunlun' => 'bp_fragment_kunlun',
            default => 'bp_fragment_nanshan',
        };
    }
}
