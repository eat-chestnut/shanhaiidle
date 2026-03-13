<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentBonusAggregator;
use PHPUnit\Framework\TestCase;

class EquipmentBonusAggregatorTest extends TestCase
{
    public function test_it_groups_same_effect_key_from_multiple_sources_and_preserves_source_without_final_conversion(): void
    {
        $aggregator = new EquipmentBonusAggregator();

        $result = $aggregator->aggregate(
            10001,
            [
                'activated_effects' => [
                    [
                        'set_id' => 'set_zhaoyao_40',
                        'piece_count' => 2,
                        'effect_key' => 'bonus_melee_atk',
                        'value_type' => 'percent',
                        'value' => 8,
                    ],
                ],
            ],
            [
                'qualified_star_links' => [
                    [
                        'required_equipment_star' => 6,
                        'effect_key' => 'bonus_skill_dmg',
                        'value_type' => 'percent',
                        'value' => 5,
                    ],
                ],
            ],
            [
                [
                    'effect_key' => 'bonus_hp',
                    'value_type' => 'flat',
                    'value' => 120,
                    'source' => 'tal_common_guard_40_tier_1',
                ],
            ],
            [
                [
                    'effect_key' => 'bonus_skill_dmg',
                    'value_type' => 'percent',
                    'value' => 8,
                    'source' => 'itm_gem_skill_qingqiuyin_blue',
                ],
            ],
            [
                [
                    'effect_key' => 'bonus_boss_dmg',
                    'value_type' => 'percent',
                    'value' => 12,
                    'source' => 'core_qingqiu_frost',
                ],
            ],
            [
                [
                    'effect_key' => 'bonus_crit_dmg',
                    'value_type' => 'percent',
                    'value' => 6,
                    'source' => 'affix_crit_dmg_45',
                ],
            ]
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            'bonus_boss_dmg' => [
                ['value_type' => 'percent', 'value' => 12, 'source' => 'core_qingqiu_frost'],
            ],
            'bonus_crit_dmg' => [
                ['value_type' => 'percent', 'value' => 6, 'source' => 'affix_crit_dmg_45'],
            ],
            'bonus_hp' => [
                ['value_type' => 'flat', 'value' => 120, 'source' => 'tal_common_guard_40_tier_1'],
            ],
            'bonus_melee_atk' => [
                ['value_type' => 'percent', 'value' => 8, 'source' => 'set_zhaoyao_40_2pc'],
            ],
            'bonus_skill_dmg' => [
                ['value_type' => 'percent', 'value' => 5, 'source' => 'talisman_star_link_6'],
                ['value_type' => 'percent', 'value' => 8, 'source' => 'itm_gem_skill_qingqiuyin_blue'],
            ],
        ], $result['data']);
    }
}
