<?php

namespace Tests\Feature\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentGemSocketService;

class EquipmentGemSocketServiceTest extends EquipmentTransactionServiceTestCase
{
    public function test_can_socket_when_slot_is_unlocked_and_type_matches(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_socket_a',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id, [1]);
        $this->createProfile($playerId, ['itm_gem_attr_chijinshi_white' => 1]);

        $result = app(EquipmentGemSocketService::class)->execute($playerId, (string) $instance->instance_id, 1, 'itm_gem_attr_chijinshi_white');

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'instance_id' => 'eq_inst_set_weapon_socket_a',
            'slot_index' => 1,
            'gem_item_id' => 'itm_gem_attr_chijinshi_white',
        ], $result['data']);
        $this->assertDatabaseHas('player_equipment_gem_slots', [
            'instance_id' => 'eq_inst_set_weapon_socket_a',
            'slot_index' => 1,
            'gem_item_id' => 'itm_gem_attr_chijinshi_white',
        ]);
    }

    public function test_fails_when_slot_is_locked(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_socket_b',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id, [1]);
        $this->createProfile($playerId, ['itm_gem_attr_chijinshi_white' => 1]);

        $result = app(EquipmentGemSocketService::class)->execute($playerId, (string) $instance->instance_id, 3, 'itm_gem_attr_chijinshi_white');

        $this->assertFalse($result['ok']);
        $this->assertSame('slot 3 is not unlocked', $result['reason']);
        $this->assertDatabaseHas('player_equipment_gem_slots', [
            'instance_id' => 'eq_inst_set_weapon_socket_b',
            'slot_index' => 3,
            'gem_item_id' => null,
        ]);
    }

    public function test_fails_when_gem_type_does_not_match_slot_type(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_socket_c',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id, [1]);
        $this->createProfile($playerId, ['itm_gem_skill_qingqiuyin_blue' => 1]);

        $result = app(EquipmentGemSocketService::class)->execute($playerId, (string) $instance->instance_id, 1, 'itm_gem_skill_qingqiuyin_blue');

        $this->assertFalse($result['ok']);
        $this->assertSame('gem item itm_gem_skill_qingqiuyin_blue does not match slot 1 type', $result['reason']);
    }

    public function test_fails_when_slot_already_has_gem(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_socket_d',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id, [1], [1 => 'itm_gem_attr_chijinshi_white']);
        $this->createProfile($playerId, ['itm_gem_attr_chijinshi_white' => 1]);

        $result = app(EquipmentGemSocketService::class)->execute($playerId, (string) $instance->instance_id, 1, 'itm_gem_attr_chijinshi_white');

        $this->assertFalse($result['ok']);
        $this->assertSame('slot 1 already has a gem', $result['reason']);
        $this->assertDatabaseHas('player_equipment_gem_slots', [
            'instance_id' => 'eq_inst_set_weapon_socket_d',
            'slot_index' => 1,
            'gem_item_id' => 'itm_gem_attr_chijinshi_white',
        ]);
    }
}
