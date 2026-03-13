<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\BlueAffixEligibilityResolver;
use PHPUnit\Framework\TestCase;

class BlueAffixEligibilityResolverTest extends TestCase
{
    public function test_blue_ring_level_45_returns_only_ring_affixes_with_same_level_band(): void
    {
        $resolver = new BlueAffixEligibilityResolver();

        $instance = [
            'instance_id' => 'eq_inst_blue_ring_45_a001',
            'item_id' => 'itm_blue_ring_45',
            'equipment_source_type' => 'blue_equipment',
            'slot_type' => 'ring_1',
        ];

        $blueTemplate = [
            'template_id' => 'blue_ring_45',
            'slot_type' => 'ring',
            'level_band' => 45,
        ];

        $blueAffixes = [
            [
                'affix_id' => 'affix_boss_dmg_45',
                'effect_key' => 'bonus_boss_dmg',
                'value_min' => 3.0,
                'value_max' => 6.0,
                'level_band' => 45,
            ],
            [
                'affix_id' => 'affix_crit_dmg_45',
                'effect_key' => 'bonus_crit_dmg',
                'value_min' => 4.0,
                'value_max' => 8.0,
                'level_band' => 45,
            ],
            [
                'affix_id' => 'affix_hp_35',
                'effect_key' => 'bonus_hp',
                'value_min' => 90,
                'value_max' => 150,
                'level_band' => 35,
            ],
            [
                'affix_id' => 'affix_melee_atk_45',
                'effect_key' => 'bonus_melee_atk',
                'value_min' => 3,
                'value_max' => 7,
                'level_band' => 45,
            ],
        ];

        $blueAffixSlotRules = [
            ['affix_id' => 'affix_boss_dmg_45', 'slot_type' => 'ring'],
            ['affix_id' => 'affix_crit_dmg_45', 'slot_type' => 'ring'],
            ['affix_id' => 'affix_hp_35', 'slot_type' => 'armor'],
            ['affix_id' => 'affix_melee_atk_45', 'slot_type' => 'main_weapon'],
        ];

        $result = $resolver->resolve($instance, $blueTemplate, $blueAffixes, $blueAffixSlotRules);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(
            ['affix_boss_dmg_45', 'affix_crit_dmg_45'],
            array_column($result['data']['eligible_affixes'], 'affix_id')
        );
    }
}
