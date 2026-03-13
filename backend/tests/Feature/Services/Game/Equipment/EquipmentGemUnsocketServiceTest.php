<?php

namespace Tests\Feature\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentGemUnsocketService;

class EquipmentGemUnsocketServiceTest extends EquipmentTransactionServiceTestCase
{
    public function test_can_unsocket_when_slot_has_gem(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_unsocket_a',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id, [1], [1 => 'itm_gem_attr_chijinshi_white']);

        $result = app(EquipmentGemUnsocketService::class)->execute($playerId, (string) $instance->instance_id, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'instance_id' => 'eq_inst_set_weapon_unsocket_a',
            'slot_index' => 1,
            'removed_gem_item_id' => 'itm_gem_attr_chijinshi_white',
        ], $result['data']);
        $this->assertDatabaseHas('player_equipment_gem_slots', [
            'instance_id' => 'eq_inst_set_weapon_unsocket_a',
            'slot_index' => 1,
            'gem_item_id' => null,
        ]);
    }

    public function test_fails_when_slot_is_empty(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_unsocket_b',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id, [1]);

        $result = app(EquipmentGemUnsocketService::class)->execute($playerId, (string) $instance->instance_id, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame('slot 1 has no gem', $result['reason']);
    }
}
