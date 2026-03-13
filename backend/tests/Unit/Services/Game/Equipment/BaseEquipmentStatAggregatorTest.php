<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\BaseEquipmentStatAggregator;
use PHPUnit\Framework\TestCase;

class BaseEquipmentStatAggregatorTest extends TestCase
{
    public function test_it_aggregates_base_white_stats_and_blue_template_stats_without_mixing_bonus_effects(): void
    {
        $aggregator = new BaseEquipmentStatAggregator();

        $result = $aggregator->aggregate(
            10001,
            [
                ['slot_type' => 'main_weapon', 'instance_id' => 'eq_inst_set_weapon_40_a001'],
                ['slot_type' => 'armor', 'instance_id' => 'eq_inst_set_armor_40_a001'],
                ['slot_type' => 'ring_1', 'instance_id' => 'eq_inst_blue_ring_45_a001'],
            ],
            [
                [
                    'instance_id' => 'eq_inst_set_weapon_40_a001',
                    'item_id' => 'itm_set_main_weapon_zhaoyao_40',
                    'slot_type' => 'main_weapon',
                ],
                [
                    'instance_id' => 'eq_inst_set_armor_40_a001',
                    'item_id' => 'itm_set_armor_zhaoyao_40',
                    'slot_type' => 'armor',
                ],
                [
                    'instance_id' => 'eq_inst_blue_ring_45_a001',
                    'item_id' => 'itm_blue_ring_45',
                    'slot_type' => 'ring_1',
                    'equipment_source_type' => 'blue_equipment',
                ],
            ],
            [
                [
                    'item_id' => 'itm_set_main_weapon_zhaoyao_40',
                    'base_stats' => [
                        ['stat_key' => 'MELEE_ATK', 'value_type' => 'flat', 'value' => 60],
                    ],
                    'activated_effects' => [
                        ['effect_key' => 'bonus_melee_atk', 'value_type' => 'percent', 'value' => 8],
                    ],
                ],
                [
                    'item_id' => 'itm_set_armor_zhaoyao_40',
                    'base_stats' => [
                        ['stat_key' => 'HP', 'value_type' => 'flat', 'value' => 220],
                        ['stat_key' => 'DEF', 'value_type' => 'flat', 'value' => 18],
                    ],
                ],
            ],
            [
                [
                    'result_item_id' => 'itm_blue_ring_45',
                    'base_stats' => [
                        ['stat_key' => 'CRIT_RATE', 'value_type' => 'percent', 'value' => 2.0],
                    ],
                ],
            ]
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            'CRIT_RATE' => 2.0,
            'DEF' => 18,
            'HP' => 220,
            'MELEE_ATK' => 60,
        ], $result['data']);
        $this->assertArrayNotHasKey('bonus_melee_atk', $result['data']);
    }
}
