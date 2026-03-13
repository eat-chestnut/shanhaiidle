<?php

namespace Database\Seeders;

use App\Models\DailyDungeon;
use App\Models\DailyDungeonLevel;
use App\Models\Item;
use App\Services\ItemCatalogImportService;
use App\Support\DailyDungeonSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DailyDungeonsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedRequiredItems();
            $this->seedDailyDungeons();
        });
    }

    private function seedRequiredItems(): void
    {
        $definitions = [
            $this->itemDefinition('mat_daily_star_sand', '星砂', 'white', 8010),
            $this->itemDefinition('mat_daily_spirit_jade', '灵玉', 'blue', 8020),
            $this->itemDefinition('mat_daily_spirit_mark', '灵印', 'purple', 8030),
            $this->itemDefinition('mat_daily_refine_soul', '淬灵髓', 'gold', 8040),
            $this->itemDefinition('mat_base_yan_mu', '棪木', 'white', 8050),
            $this->itemDefinition('mat_base_mi_gu_branch', '迷榖枝', 'white', 8060),
            $this->itemDefinition('mat_base_zhu_yu_leaf', '祝余叶', 'white', 8070),
            $this->itemDefinition('mat_stage_queshan_remnant', '雀山残片', 'blue', 8080),
            $this->itemDefinition('mat_stage_zhaoyao_bone', '招摇祭骨', 'blue', 8090),
            $this->itemDefinition('mat_stage_qingqiu_seal', '青丘封符', 'purple', 8100),
            $this->itemDefinition('mat_stage_jiwei_stone', '箕尾镇脉石', 'purple', 8110),
        ];

        foreach ($definitions as $definition) {
            Item::query()->updateOrCreate(
                ['item_id' => $definition['item_id']],
                $definition,
            );
        }
    }

    private function seedDailyDungeons(): void
    {
        $monsterStages = [
            'stage_04' => ['stage_04_normal_a', 'stage_04_normal_b', 'stage_04_elite', 'stage_04_boss'],
            'stage_05' => ['stage_05_normal_a', 'stage_05_normal_b', 'stage_05_elite', 'stage_05_boss'],
            'stage_06' => ['stage_06_normal_a', 'stage_06_normal_b', 'stage_06_elite', 'stage_06_boss'],
            'stage_07' => ['stage_07_normal_a', 'stage_07_normal_b', 'stage_07_elite', 'stage_07_boss'],
            'stage_08' => ['stage_08_normal_a', 'stage_08_normal_b', 'stage_08_elite', 'stage_08_boss'],
        ];

        $definitions = [
            [
                'dungeon_id' => 'dun_star_sand',
                'title' => '星砂副本',
                'display_name' => '星砂副本',
                'dungeon_type' => 'star_sand',
                'unlock_level' => 23,
                'entry_cost_item_id' => null,
                'entry_cost_count' => 0,
                'daily_limit' => 2,
                'sweep_enabled' => false,
                'icon' => 'icon_daily_star_sand',
                'summary' => '常驻星砂日常副本，采用 5 级升级制，升级后提高怪物强度与掉落质量。',
                'sort_order' => 10,
                'is_enabled' => true,
                'remark' => '正式首版常驻日常副本。',
                'levels' => [
                    $this->levelDefinition('dun_star_sand', 1, 23, 1200, false, $monsterStages['stage_04'], [2, 3], [
                        $this->upgradeCost(2, 'cur_gold', 2000, 10),
                        $this->upgradeCost(2, 'mat_base_gui_mu', 12, 20),
                        $this->upgradeCost(2, 'mat_base_yan_mu', 12, 30),
                    ], [
                        $this->reward('mat_daily_star_sand', 20, 10),
                        $this->reward('cur_gold', 2000, 20),
                    ]),
                    $this->levelDefinition('dun_star_sand', 2, 28, 1800, false, $monsterStages['stage_05'], [2, 3], [
                        $this->upgradeCost(3, 'cur_gold', 5000, 10),
                        $this->upgradeCost(3, 'mat_base_mi_gu_branch', 10, 20),
                        $this->upgradeCost(3, 'mat_base_zhu_yu_leaf', 10, 30),
                    ], [
                        $this->reward('mat_daily_star_sand', 30, 10),
                        $this->reward('cur_gold', 3200, 20),
                    ]),
                    $this->levelDefinition('dun_star_sand', 3, 34, 2600, false, $monsterStages['stage_06'], [3, 4], [
                        $this->upgradeCost(4, 'cur_gold', 9000, 10),
                        $this->upgradeCost(4, 'mat_stage_queshan_remnant', 6, 20),
                        $this->upgradeCost(4, 'mat_stage_zhaoyao_bone', 6, 30),
                    ], [
                        $this->reward('mat_daily_star_sand', 40, 10),
                        $this->reward('cur_gold', 4600, 20),
                    ]),
                    $this->levelDefinition('dun_star_sand', 4, 40, 3600, false, $monsterStages['stage_07'], [3, 4], [
                        $this->upgradeCost(5, 'cur_gold', 15000, 10),
                        $this->upgradeCost(5, 'mat_stage_qingqiu_seal', 4, 20),
                        $this->upgradeCost(5, 'mat_stage_jiwei_stone', 2, 30),
                    ], [
                        $this->reward('mat_daily_star_sand', 50, 10),
                        $this->reward('cur_gold', 6200, 20),
                    ]),
                    $this->levelDefinition('dun_star_sand', 5, 46, 4800, true, $monsterStages['stage_08'], [4, 5], [], [
                        $this->reward('mat_daily_star_sand', 60, 10),
                        $this->reward('cur_gold', 8000, 20),
                    ]),
                ],
            ],
            [
                'dungeon_id' => 'dun_spirit_jade',
                'title' => '灵玉副本',
                'display_name' => '灵玉副本',
                'dungeon_type' => 'spirit_jade',
                'unlock_level' => 28,
                'entry_cost_item_id' => null,
                'entry_cost_count' => 0,
                'daily_limit' => 2,
                'sweep_enabled' => false,
                'icon' => 'icon_daily_spirit_jade',
                'summary' => '常驻灵玉日常副本，副本等级通过消耗材料升级，不做自动难度解锁。',
                'sort_order' => 20,
                'is_enabled' => true,
                'remark' => '正式首版常驻日常副本。',
                'levels' => [
                    $this->levelDefinition('dun_spirit_jade', 1, 28, 1800, false, $monsterStages['stage_05'], [2, 3], [
                        $this->upgradeCost(2, 'cur_gold', 2600, 10),
                        $this->upgradeCost(2, 'mat_base_gui_mu', 8, 20),
                        $this->upgradeCost(2, 'mat_base_yan_mu', 8, 30),
                    ], [
                        $this->reward('mat_daily_spirit_jade', 12, 10),
                        $this->reward('cur_gold', 2500, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_jade', 2, 33, 2500, false, $monsterStages['stage_06'], [3, 4], [], [
                        $this->reward('mat_daily_spirit_jade', 18, 10),
                        $this->reward('cur_gold', 3600, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_jade', 3, 39, 3400, false, $monsterStages['stage_07'], [3, 4], [], [
                        $this->reward('mat_daily_spirit_jade', 24, 10),
                        $this->reward('cur_gold', 5000, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_jade', 4, 45, 4600, false, $monsterStages['stage_08'], [4, 5], [], [
                        $this->reward('mat_daily_spirit_jade', 30, 10),
                        $this->reward('cur_gold', 6800, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_jade', 5, 52, 6000, true, $monsterStages['stage_08'], [5, 6], [], [
                        $this->reward('mat_daily_spirit_jade', 36, 10),
                        $this->reward('cur_gold', 9000, 20),
                    ]),
                ],
            ],
            [
                'dungeon_id' => 'dun_spirit_mark',
                'title' => '灵印副本',
                'display_name' => '灵印副本',
                'dungeon_type' => 'spirit_mark',
                'unlock_level' => 35,
                'entry_cost_item_id' => null,
                'entry_cost_count' => 0,
                'daily_limit' => 2,
                'sweep_enabled' => false,
                'icon' => 'icon_daily_spirit_mark',
                'summary' => '常驻灵印日常副本，等级越高，怪物编成越完整，掉落表现越好。',
                'sort_order' => 30,
                'is_enabled' => true,
                'remark' => '正式首版常驻日常副本。',
                'levels' => [
                    $this->levelDefinition('dun_spirit_mark', 1, 35, 2600, false, $monsterStages['stage_06'], [2, 3], [
                        $this->upgradeCost(2, 'cur_gold', 3200, 10),
                        $this->upgradeCost(2, 'mat_base_mi_gu_branch', 8, 20),
                    ], [
                        $this->reward('mat_daily_spirit_mark', 8, 10),
                        $this->reward('cur_gold', 3200, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_mark', 2, 40, 3500, false, $monsterStages['stage_07'], [3, 4], [], [
                        $this->reward('mat_daily_spirit_mark', 12, 10),
                        $this->reward('cur_gold', 4500, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_mark', 3, 46, 4600, false, $monsterStages['stage_08'], [4, 5], [], [
                        $this->reward('mat_daily_spirit_mark', 16, 10),
                        $this->reward('cur_gold', 6200, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_mark', 4, 52, 6000, false, $monsterStages['stage_08'], [5, 6], [], [
                        $this->reward('mat_daily_spirit_mark', 20, 10),
                        $this->reward('cur_gold', 8400, 20),
                    ]),
                    $this->levelDefinition('dun_spirit_mark', 5, 58, 7600, true, $monsterStages['stage_08'], [6, 7], [], [
                        $this->reward('mat_daily_spirit_mark', 24, 10),
                        $this->reward('cur_gold', 10800, 20),
                    ]),
                ],
            ],
            [
                'dungeon_id' => 'dun_refine_soul',
                'title' => '淬灵副本',
                'display_name' => '淬灵副本',
                'dungeon_type' => 'refine_soul',
                'unlock_level' => 45,
                'entry_cost_item_id' => null,
                'entry_cost_count' => 0,
                'daily_limit' => 2,
                'sweep_enabled' => false,
                'icon' => 'icon_daily_refine_soul',
                'summary' => '常驻淬灵日常副本，采用固定 5 级升级制，扫荡仅保留布尔开关。',
                'sort_order' => 40,
                'is_enabled' => true,
                'remark' => '正式首版常驻日常副本。',
                'levels' => [
                    $this->levelDefinition('dun_refine_soul', 1, 45, 4200, false, $monsterStages['stage_07'], [3, 4], [
                        $this->upgradeCost(2, 'cur_gold', 4800, 10),
                        $this->upgradeCost(2, 'mat_base_zhu_yu_leaf', 8, 20),
                    ], [
                        $this->reward('mat_daily_refine_soul', 6, 10),
                        $this->reward('cur_gold', 4200, 20),
                    ]),
                    $this->levelDefinition('dun_refine_soul', 2, 50, 5200, false, $monsterStages['stage_08'], [4, 5], [], [
                        $this->reward('mat_daily_refine_soul', 9, 10),
                        $this->reward('cur_gold', 5600, 20),
                    ]),
                    $this->levelDefinition('dun_refine_soul', 3, 56, 6500, false, $monsterStages['stage_08'], [5, 6], [], [
                        $this->reward('mat_daily_refine_soul', 12, 10),
                        $this->reward('cur_gold', 7600, 20),
                    ]),
                    $this->levelDefinition('dun_refine_soul', 4, 62, 7900, false, $monsterStages['stage_08'], [6, 7], [], [
                        $this->reward('mat_daily_refine_soul', 15, 10),
                        $this->reward('cur_gold', 9800, 20),
                    ]),
                    $this->levelDefinition('dun_refine_soul', 5, 68, 9500, true, $monsterStages['stage_08'], [7, 8], [], [
                        $this->reward('mat_daily_refine_soul', 18, 10),
                        $this->reward('cur_gold', 12200, 20),
                    ]),
                ],
            ],
        ];

        $validDungeonIds = [];
        $validLevelIds = [];

        foreach ($definitions as $definition) {
            $validDungeonIds[] = $definition['dungeon_id'];

            DailyDungeon::query()->updateOrCreate(
                ['dungeon_id' => $definition['dungeon_id']],
                [
                    'title' => $definition['title'],
                    'display_name' => $definition['display_name'],
                    'dungeon_type' => $definition['dungeon_type'],
                    'unlock_level' => $definition['unlock_level'],
                    'entry_cost_item_id' => $definition['entry_cost_item_id'],
                    'entry_cost_count' => $definition['entry_cost_count'],
                    'daily_limit' => $definition['daily_limit'],
                    'sweep_enabled' => $definition['sweep_enabled'],
                    'icon' => $definition['icon'],
                    'summary' => $definition['summary'],
                    'sort_order' => $definition['sort_order'],
                    'is_enabled' => $definition['is_enabled'],
                    'remark' => $definition['remark'],
                ],
            );

            foreach ($definition['levels'] as $levelDefinition) {
                $validLevelIds[] = $levelDefinition['dungeon_level_id'];

                $level = DailyDungeonLevel::query()->updateOrCreate(
                    ['dungeon_level_id' => $levelDefinition['dungeon_level_id']],
                    [
                        'dungeon_id' => $definition['dungeon_id'],
                        'level_no' => $levelDefinition['level_no'],
                        'level_name' => $levelDefinition['level_name'],
                        'recommended_level' => $levelDefinition['recommended_level'],
                        'recommended_power' => $levelDefinition['recommended_power'],
                        'is_max_level' => $levelDefinition['is_max_level'],
                        'summary' => $levelDefinition['summary'],
                        'sort_order' => $levelDefinition['sort_order'],
                        'is_enabled' => true,
                        'remark' => null,
                    ],
                );

                $level->monsterEntries()->delete();
                foreach ($levelDefinition['monster_entries'] as $monsterEntry) {
                    $level->monsterEntries()->create($monsterEntry);
                }

                $level->upgradeCosts()->delete();
                foreach ($levelDefinition['upgrade_costs'] as $upgradeCost) {
                    $level->upgradeCosts()->create($upgradeCost);
                }

                $level->firstClearRewards()->delete();
                foreach ($levelDefinition['first_clear_rewards'] as $reward) {
                    $level->firstClearRewards()->create($reward);
                }
            }
        }

        DailyDungeonLevel::query()->whereNotIn('dungeon_level_id', $validLevelIds)->delete();
        DailyDungeon::query()->whereNotIn('dungeon_id', $validDungeonIds)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemDefinition(string $itemId, string $displayName, string $quality, int $sortOrder): array
    {
        return [
            'id' => $itemId,
            'item_id' => $itemId,
            'item_name' => $displayName,
            'display_name' => $displayName,
            'main_type' => 'material',
            'sub_type' => 'function_material',
            'quality' => $quality,
            'rarity' => $quality,
            'icon' => null,
            'desc' => sprintf('%s模块最小可用样例物品。', $displayName),
            'is_stackable' => true,
            'max_stack' => 9999,
            'is_enabled' => true,
            'sort_order' => $sortOrder,
            'remark' => 'daily_dungeon_module seeded item',
            'source_library' => 'daily_dungeon_module',
            'required_level' => 1,
            'bind_type' => 'none',
            'sell_price' => 0,
            'use_type' => 'craft_material',
            'rarity_frame_key' => null,
            'name' => $displayName,
            'type' => ItemCatalogImportService::legacyType('material', 'function_material'),
            'material_type' => ItemCatalogImportService::legacyMaterialType('material', 'function_material'),
            'trait' => null,
            'effect_type' => null,
            'target_scope' => null,
            'effect_payload' => null,
            'drop_unlock_level' => 1,
            'socket_limit' => null,
            'source_tags' => ['daily_dungeon'],
            'use_tags' => [],
            'can_compose' => false,
            'can_reforge' => false,
            'stack_limit' => 9999,
        ];
    }

    /**
     * @param  array<int, string>  $monsterIds
     * @param  array{0:int,1:int}  $normalCounts
     * @param  array<int, array<string, mixed>>  $upgradeCosts
     * @param  array<int, array<string, mixed>>  $firstClearRewards
     * @return array<string, mixed>
     */
    private function levelDefinition(
        string $dungeonId,
        int $levelNo,
        int $recommendedLevel,
        int $recommendedPower,
        bool $isMaxLevel,
        array $monsterIds,
        array $normalCounts,
        array $upgradeCosts,
        array $firstClearRewards,
    ): array {
        [$normalA, $normalB, $elite, $boss] = $monsterIds;

        return [
            'dungeon_level_id' => sprintf('%s_lv%d', $dungeonId, $levelNo),
            'level_no' => $levelNo,
            'level_name' => DailyDungeonSupport::levelNameSamples()[$levelNo],
            'recommended_level' => $recommendedLevel,
            'recommended_power' => $recommendedPower,
            'is_max_level' => $isMaxLevel,
            'summary' => sprintf('Lv%d %s，建议等级 %d，推荐战力 %d。', $levelNo, DailyDungeonSupport::levelNameSamples()[$levelNo], $recommendedLevel, $recommendedPower),
            'sort_order' => $levelNo * 10,
            'monster_entries' => [
                $this->monsterEntry($normalA, 'normal', 100, $normalCounts[0], $normalCounts[1], 10),
                $this->monsterEntry($normalB, 'normal', 90, $normalCounts[0], $normalCounts[1], 20),
                $this->monsterEntry($elite, 'elite', 100, 1, $levelNo >= 4 ? 2 : 1, 30),
                $this->monsterEntry($boss, 'boss', 100, 1, 1, 40),
            ],
            'upgrade_costs' => $upgradeCosts,
            'first_clear_rewards' => $firstClearRewards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function monsterEntry(
        string $monsterId,
        string $spawnType,
        int $weight,
        int $minCount,
        int $maxCount,
        int $sortOrder,
    ): array {
        return [
            'monster_id' => $monsterId,
            'spawn_type' => $spawnType,
            'weight' => $weight,
            'min_count' => $minCount,
            'max_count' => $maxCount,
            'sort_order' => $sortOrder,
            'is_enabled' => true,
            'remark' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function upgradeCost(int $targetLevelNo, string $itemId, int $count, int $sortOrder): array
    {
        return [
            'target_level_no' => $targetLevelNo,
            'item_id' => $itemId,
            'count' => $count,
            'sort_order' => $sortOrder,
            'is_enabled' => true,
            'remark' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reward(string $itemId, int $count, int $sortOrder): array
    {
        return [
            'item_id' => $itemId,
            'count' => $count,
            'sort_order' => $sortOrder,
            'is_enabled' => true,
            'remark' => null,
        ];
    }
}
