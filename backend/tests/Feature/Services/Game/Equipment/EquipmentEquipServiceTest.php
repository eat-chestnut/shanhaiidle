<?php

namespace Tests\Feature\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentEquipService;

class EquipmentEquipServiceTest extends EquipmentTransactionServiceTestCase
{
    public function test_can_equip_to_empty_slot(): void
    {
        $this->seedEquipmentTransactionDependencies(false);

        $playerId = 10001;
        $instance = $this->createInstance($playerId, 'eq_inst_ring_a', 'itm_blue_ring_45', 'blue_equipment', 'ring_1');

        $result = app(EquipmentEquipService::class)->execute($playerId, (string) $instance->instance_id, 'ring_1');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            'equipped_instance_id' => 'eq_inst_ring_a',
            'target_slot_type' => 'ring_1',
            'replaced_instance_id' => null,
        ], $result['data']);

        $instance->refresh();
        $this->assertTrue($instance->is_equipped);
        $this->assertSame('ring_1', $instance->slot_type);
        $this->assertDatabaseHas('player_equipment_loadouts', [
            'player_id' => $playerId,
            'slot_type' => 'ring_1',
            'instance_id' => 'eq_inst_ring_a',
        ]);
    }

    public function test_can_replace_existing_equipment(): void
    {
        $this->seedEquipmentTransactionDependencies(false);

        $playerId = 10001;
        $oldInstance = $this->createInstance($playerId, 'eq_inst_ring_old', 'itm_blue_ring_45', 'blue_equipment', 'ring_1', null, null, 0, 0, true);
        $newInstance = $this->createInstance($playerId, 'eq_inst_ring_new', 'itm_blue_ring_45', 'blue_equipment', 'ring_1');
        $this->createLoadout($playerId, 'ring_1', (string) $oldInstance->instance_id);

        $result = app(EquipmentEquipService::class)->execute($playerId, (string) $newInstance->instance_id, 'ring_1');

        $this->assertTrue($result['ok']);
        $this->assertSame('eq_inst_ring_old', $result['data']['replaced_instance_id']);

        $oldInstance->refresh();
        $newInstance->refresh();
        $this->assertFalse($oldInstance->is_equipped);
        $this->assertTrue($newInstance->is_equipped);
        $this->assertDatabaseHas('player_equipment_loadouts', [
            'player_id' => $playerId,
            'slot_type' => 'ring_1',
            'instance_id' => 'eq_inst_ring_new',
        ]);
    }

    public function test_talisman_cannot_equip_to_normal_slot(): void
    {
        $this->seedEquipmentTransactionDependencies(false);

        $playerId = 10001;
        $instance = $this->createInstance($playerId, 'eq_inst_talisman_a', 'itm_talisman_bing_40', 'common_equipment', 'talisman');

        $result = app(EquipmentEquipService::class)->execute($playerId, (string) $instance->instance_id, 'armor');

        $this->assertFalse($result['ok']);
        $this->assertSame('instance slot type talisman is not compatible with target slot armor', $result['reason']);
        $this->assertDatabaseCount('player_equipment_loadouts', 0);
    }

    public function test_bracelet_and_ring_can_use_dual_slots(): void
    {
        $this->seedEquipmentTransactionDependencies(false);

        $playerId = 10001;
        $bracelet = $this->createInstance($playerId, 'eq_inst_bracelet_a', 'itm_blue_bracelet_35', 'blue_equipment', 'bracelet_1');
        $ring = $this->createInstance($playerId, 'eq_inst_ring_b', 'itm_blue_ring_45', 'blue_equipment', 'ring_1');

        $braceletResult = app(EquipmentEquipService::class)->execute($playerId, (string) $bracelet->instance_id, 'bracelet_2');
        $ringResult = app(EquipmentEquipService::class)->execute($playerId, (string) $ring->instance_id, 'ring_2');

        $this->assertTrue($braceletResult['ok']);
        $this->assertTrue($ringResult['ok']);

        $bracelet->refresh();
        $ring->refresh();
        $this->assertSame('bracelet_2', $bracelet->slot_type);
        $this->assertSame('ring_2', $ring->slot_type);
        $this->assertDatabaseHas('player_equipment_loadouts', [
            'player_id' => $playerId,
            'slot_type' => 'bracelet_2',
            'instance_id' => 'eq_inst_bracelet_a',
        ]);
        $this->assertDatabaseHas('player_equipment_loadouts', [
            'player_id' => $playerId,
            'slot_type' => 'ring_2',
            'instance_id' => 'eq_inst_ring_b',
        ]);
    }
}
