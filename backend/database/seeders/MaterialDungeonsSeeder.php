<?php

namespace Database\Seeders;

use App\Models\MaterialDungeon;
use App\Models\MaterialDungeonDropGroup;
use App\Support\MaterialDungeonSupport;
use Illuminate\Database\Seeder;

class MaterialDungeonsSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'dungeon_id' => 'daily_gold',
                'name' => '堂庭采金台',
                'dungeon_type' => 'gold',
                'unlock_level' => 3,
                'unlock_stage_id' => 'nan_01',
                'display_rewards' => ['金币'],
                'layers' => [
                    [
                        'layer' => 1,
                        'recommended_power' => 220,
                        'group_id' => 'mdg_daily_gold_drop',
                        'name' => '堂庭采金台 掉落',
                        'rewards' => [
                            $this->reward('金币', 160, 220),
                        ],
                    ],
                ],
                'level_configs' => [
                    $this->levelConfig(1, 1.0, []),
                    $this->levelConfig(2, 1.4, [['item_id' => '桂木', 'count' => 6]]),
                    $this->levelConfig(3, 1.8, [['item_id' => '祝余叶', 'count' => 8], ['item_id' => '棪木', 'count' => 4]]),
                    $this->levelConfig(4, 2.3, [['item_id' => '白玉髓', 'count' => 6], ['item_id' => '水玉晶', 'count' => 4]]),
                    $this->levelConfig(5, 2.9, [['item_id' => '鹿蜀纹角碎片', 'count' => 5], ['item_id' => '白玉髓', 'count' => 8]]),
                ],
                'stamina_cost' => 8,
                'daily_limit' => 4,
                'description' => '基础金币补给副本。升级后仅提高金币产出，不提高战斗强度。',
                'sort_order' => 10,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'daily_exp',
                'name' => '招摇修行阵',
                'dungeon_type' => 'exp',
                'unlock_level' => 6,
                'unlock_stage_id' => 'nan_02',
                'display_rewards' => ['角色经验'],
                'layers' => [
                    [
                        'layer' => 1,
                        'recommended_power' => 480,
                        'group_id' => 'mdg_daily_exp_drop',
                        'name' => '招摇修行阵 掉落',
                        'rewards' => [
                            $this->reward('角色经验', 22, 28),
                        ],
                    ],
                ],
                'level_configs' => [
                    $this->levelConfig(1, 1.0, []),
                    $this->levelConfig(2, 1.35, [['item_id' => '桂木', 'count' => 8]]),
                    $this->levelConfig(3, 1.7, [['item_id' => '祝余叶', 'count' => 10], ['item_id' => '棪木', 'count' => 5]]),
                    $this->levelConfig(4, 2.1, [['item_id' => '白玉髓', 'count' => 8], ['item_id' => '水玉晶', 'count' => 6]]),
                    $this->levelConfig(5, 2.6, [['item_id' => '鹿蜀纹角碎片', 'count' => 6], ['item_id' => '白玉髓', 'count' => 10]]),
                ],
                'stamina_cost' => 10,
                'daily_limit' => 4,
                'description' => '角色经验补给副本。适合 1-20 级稳定追等级。',
                'sort_order' => 20,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'daily_material',
                'name' => '祝余工坊谷',
                'dungeon_type' => 'material',
                'unlock_level' => 10,
                'unlock_stage_id' => 'nan_03',
                'display_rewards' => ['桂木', '棪木', '白玉髓', '水玉晶'],
                'layers' => [
                    [
                        'layer' => 1,
                        'recommended_power' => 920,
                        'group_id' => 'mdg_daily_material_drop',
                        'name' => '祝余工坊谷 掉落',
                        'rewards' => [
                            $this->reward('桂木', 2, 3),
                            $this->reward('棪木', 1, 2),
                            $this->reward('白玉髓', 1, 2),
                            $this->reward('水玉晶', 1, 1, 0.7),
                        ],
                    ],
                ],
                'level_configs' => [
                    $this->levelConfig(1, 1.0, []),
                    $this->levelConfig(2, 1.3, [['item_id' => '祝余叶', 'count' => 10]]),
                    $this->levelConfig(3, 1.6, [['item_id' => '棪木', 'count' => 8], ['item_id' => '白玉髓', 'count' => 4]]),
                    $this->levelConfig(4, 2.0, [['item_id' => '水玉晶', 'count' => 8], ['item_id' => '鹿蜀纹角碎片', 'count' => 4]]),
                    $this->levelConfig(5, 2.5, [['item_id' => '鹿蜀纹角碎片', 'count' => 8], ['item_id' => '鹿蜀尾鬃', 'count' => 3]]),
                ],
                'stamina_cost' => 10,
                'daily_limit' => 5,
                'description' => '装备打造与升星的基础材料副本，升级后提高材料数量。',
                'sort_order' => 30,
                'is_enabled' => true,
            ],
            [
                'dungeon_id' => 'daily_gem',
                'name' => '基山灵璧窟',
                'dungeon_type' => 'gem',
                'unlock_level' => 15,
                'unlock_stage_id' => 'nan_04',
                'display_rewards' => ['属性宝石碎片·白蓝', '白蓝属性宝石', '技能宝石碎片'],
                'layers' => [
                    [
                        'layer' => 1,
                        'recommended_power' => 1500,
                        'group_id' => 'mdg_daily_gem_drop',
                        'name' => '基山灵璧窟 掉落',
                        'rewards' => [
                            $this->reward('属性宝石碎片·白蓝', 2, 3),
                            $this->reward('白蓝属性宝石', 1, 1, 0.45),
                            $this->reward('技能宝石碎片', 1, 2, 0.35),
                        ],
                    ],
                ],
                'level_configs' => [
                    $this->levelConfig(1, 1.0, []),
                    $this->levelConfig(2, 1.25, [['item_id' => '白玉髓', 'count' => 8], ['item_id' => '水玉晶', 'count' => 6]]),
                    $this->levelConfig(3, 1.55, [['item_id' => '鹿蜀纹角碎片', 'count' => 6], ['item_id' => '鹿蜀尾鬃', 'count' => 2]]),
                    $this->levelConfig(4, 1.9, [['item_id' => '裂渊石', 'count' => 4], ['item_id' => '深层白玉髓', 'count' => 3]]),
                    $this->levelConfig(5, 2.3, [['item_id' => '裂渊石', 'count' => 6], ['item_id' => '深渊水晶', 'count' => 2]]),
                ],
                'stamina_cost' => 12,
                'daily_limit' => 3,
                'description' => '1-20 阶段的轻量宝石补给副本，先提供碎片与低阶宝石。',
                'sort_order' => 40,
                'is_enabled' => true,
            ],
        ];

        $validDungeonIds = [];
        $validGroupIds = [];

        foreach ($definitions as $definition) {
            $validDungeonIds[] = $definition['dungeon_id'];
            $layerRules = [];

            foreach ($definition['layers'] as $index => $layerDefinition) {
                $validGroupIds[] = $layerDefinition['group_id'];

                MaterialDungeonDropGroup::query()->updateOrCreate(
                    ['group_id' => $layerDefinition['group_id']],
                    [
                        'name' => $layerDefinition['name'],
                        'rewards' => $this->rewardEntries($layerDefinition['rewards']),
                        'description' => null,
                        'sort_order' => ($definition['sort_order'] * 10) + ($index + 1),
                        'is_enabled' => true,
                    ],
                );

                $layerRules[] = [
                    'layer' => $layerDefinition['layer'],
                    'drop_group_id' => $layerDefinition['group_id'],
                    'first_clear_reward_group_id' => null,
                    'recommended_power' => $layerDefinition['recommended_power'],
                    'sort' => $index + 1,
                ];
            }

            MaterialDungeon::query()->updateOrCreate(
                ['dungeon_id' => $definition['dungeon_id']],
                [
                    'name' => $definition['name'],
                    'dungeon_type' => $definition['dungeon_type'],
                    'unlock_level' => $definition['unlock_level'],
                    'unlock_stage_id' => $definition['unlock_stage_id'],
                    'display_rewards' => $this->displayRewards($definition['display_rewards']),
                    'layer_rules' => $layerRules,
                    'level_configs' => $definition['level_configs'],
                    'stamina_cost' => $definition['stamina_cost'],
                    'daily_limit' => $definition['daily_limit'],
                    'description' => $definition['description'],
                    'sort_order' => $definition['sort_order'],
                    'is_enabled' => $definition['is_enabled'],
                ],
            );
        }

        MaterialDungeon::query()->whereNotIn('dungeon_id', $validDungeonIds)->delete();
        MaterialDungeonDropGroup::query()->whereNotIn('group_id', $validGroupIds)->delete();
    }

    /**
     * @param  array<int, string>  $itemIds
     * @return array<int, string>
     */
    private function displayRewards(array $itemIds): array
    {
        $resolved = [];

        foreach ($itemIds as $itemId) {
            $resolvedItemId = MaterialDungeonSupport::resolveLegacyItemId($itemId);
            if ($resolvedItemId === null) {
                continue;
            }

            $resolved[] = $resolvedItemId;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $rows
     * @return array<int, array<string, int|float|string>>
     */
    private function rewardEntries(array $rows): array
    {
        $entries = [];

        foreach ($rows as $index => $row) {
            $itemId = MaterialDungeonSupport::resolveLegacyItemId((string) ($row['item_id'] ?? ''));
            if ($itemId === null) {
                continue;
            }

            $entries[] = [
                'item_id' => $itemId,
                'count_min' => (int) ($row['count_min'] ?? 1),
                'count_max' => (int) ($row['count_max'] ?? 1),
                'probability' => $row['probability'] ?? 1,
                'sort' => $index + 1,
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, int|float|string>
     */
    private function reward(string $itemId, int $countMin, int $countMax, float $probability = 1): array
    {
        return [
            'item_id' => $itemId,
            'count_min' => $countMin,
            'count_max' => $countMax,
            'probability' => $probability,
        ];
    }

    /**
     * @param  array<int, array{item_id:string,count:int}>  $upgradeCosts
     * @return array<string, mixed>
     */
    private function levelConfig(int $level, float $rewardMultiplier, array $upgradeCosts): array
    {
        return [
            'level' => $level,
            'reward_multiplier' => $rewardMultiplier,
            'upgrade_costs' => array_values(array_map(
                fn (array $row, int $index): array => [
                    'item_id' => $row['item_id'],
                    'count' => $row['count'],
                    'sort' => $index + 1,
                ],
                $upgradeCosts,
                array_keys($upgradeCosts),
            )),
            'sort' => $level,
        ];
    }
}
