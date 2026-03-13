<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentStageProgressionResolver;
use PHPUnit\Framework\TestCase;

class EquipmentStageProgressionResolverTest extends TestCase
{
    public function test_allow_progression_when_current_star_reaches_required_max_star(): void
    {
        $resolver = new EquipmentStageProgressionResolver();

        $instance = [
            'instance_id' => 'eq_inst_set_weapon_20_a001',
            'item_id' => 'itm_set_main_weapon_zhaoyao_20',
            'equipment_source_type' => 'set_equipment',
            'slot_type' => 'main_weapon',
            'set_level' => 20,
            'star' => 3,
            'max_star' => 3,
        ];

        $progressionRules = [[
            'from_set_level' => 20,
            'to_set_level' => 40,
            'required_max_star' => 3,
            'star_keep_mode' => 'keep_current_star',
        ]];

        $recipeMap = [[
            'from_set_level' => 20,
            'to_set_level' => 40,
            'slot_type' => 'main_weapon',
            'result_item_id' => 'itm_set_main_weapon_zhaoyao_40',
        ]];

        $result = $resolver->resolve($instance, $progressionRules, $recipeMap);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertTrue($result['data']['can_progress']);
        $this->assertSame([
            'item_id' => 'itm_set_main_weapon_zhaoyao_40',
            'set_level' => 40,
            'star' => 3,
            'max_star' => 6,
        ], $result['data']['next_state']);
    }

    public function test_reject_progression_when_current_star_is_not_max(): void
    {
        $resolver = new EquipmentStageProgressionResolver();

        $instance = [
            'instance_id' => 'eq_inst_set_weapon_20_a001',
            'item_id' => 'itm_set_main_weapon_zhaoyao_20',
            'equipment_source_type' => 'set_equipment',
            'slot_type' => 'main_weapon',
            'set_level' => 20,
            'star' => 2,
            'max_star' => 3,
        ];

        $progressionRules = [[
            'from_set_level' => 20,
            'to_set_level' => 40,
            'required_max_star' => 3,
            'star_keep_mode' => 'keep_current_star',
        ]];

        $recipeMap = [[
            'from_set_level' => 20,
            'to_set_level' => 40,
            'slot_type' => 'main_weapon',
            'result_item_id' => 'itm_set_main_weapon_zhaoyao_40',
        ]];

        $result = $resolver->resolve($instance, $progressionRules, $recipeMap);

        $this->assertFalse($result['ok']);
        $this->assertSame('current_star_below_required_max_star', $result['reason']);
        $this->assertFalse($result['data']['can_progress']);
        $this->assertNull($result['data']['next_state']);
    }
}
