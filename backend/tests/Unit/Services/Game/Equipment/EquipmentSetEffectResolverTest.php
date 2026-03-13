<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentSetEffectResolver;
use PHPUnit\Framework\TestCase;

class EquipmentSetEffectResolverTest extends TestCase
{
    public function test_resolve_counts_same_set_excludes_talisman_and_activates_2_4_6_8_effects(): void
    {
        $resolver = new EquipmentSetEffectResolver();

        $loadouts = [
            ['slot_type' => 'main_weapon', 'instance_id' => 'eq_inst_1'],
            ['slot_type' => 'sub_weapon', 'instance_id' => 'eq_inst_2'],
            ['slot_type' => 'armor', 'instance_id' => 'eq_inst_3'],
            ['slot_type' => 'leg', 'instance_id' => 'eq_inst_4'],
            ['slot_type' => 'shoe', 'instance_id' => 'eq_inst_5'],
            ['slot_type' => 'cloak', 'instance_id' => 'eq_inst_6'],
            ['slot_type' => 'helmet', 'instance_id' => 'eq_inst_7'],
            ['slot_type' => 'necklace', 'instance_id' => 'eq_inst_8'],
            ['slot_type' => 'talisman', 'instance_id' => 'eq_inst_talisman'],
        ];

        $equipmentInstances = [
            ['instance_id' => 'eq_inst_1', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'main_weapon'],
            ['instance_id' => 'eq_inst_2', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'sub_weapon'],
            ['instance_id' => 'eq_inst_3', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'armor'],
            ['instance_id' => 'eq_inst_4', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'leg'],
            ['instance_id' => 'eq_inst_5', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'shoe'],
            ['instance_id' => 'eq_inst_6', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'cloak'],
            ['instance_id' => 'eq_inst_7', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'helmet'],
            ['instance_id' => 'eq_inst_8', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'necklace'],
            ['instance_id' => 'eq_inst_talisman', 'set_id' => 'set_zhaoyao_40', 'slot_type' => 'talisman'],
        ];

        $setEffects = [
            ['set_id' => 'set_zhaoyao_40', 'piece_count' => 2, 'effect_key' => 'bonus_a', 'value_type' => 'percent', 'value' => 8],
            ['set_id' => 'set_zhaoyao_40', 'piece_count' => 4, 'effect_key' => 'bonus_b', 'value_type' => 'percent', 'value' => 6],
            ['set_id' => 'set_zhaoyao_40', 'piece_count' => 6, 'effect_key' => 'bonus_c', 'value_type' => 'percent', 'value' => 12],
            ['set_id' => 'set_zhaoyao_40', 'piece_count' => 8, 'effect_key' => 'bonus_d', 'value_type' => 'percent', 'value' => 20],
            ['set_id' => 'set_zhaoyao_40', 'piece_count' => 10, 'effect_key' => 'bonus_e', 'value_type' => 'percent', 'value' => 30],
        ];

        $result = $resolver->resolve($loadouts, $equipmentInstances, $setEffects);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            [
                'set_id' => 'set_zhaoyao_40',
                'equipped_count' => 8,
            ],
        ], $result['data']['set_counts']);
        $this->assertSame([2, 4, 6, 8], array_column($result['data']['activated_effects'], 'piece_count'));
    }
}
