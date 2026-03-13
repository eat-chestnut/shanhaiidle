<?php

namespace Tests\Feature\Services\Game\Equipment;

use App\Services\Game\Equipment\PlayerEquipmentStatAggregator;
use Database\Seeders\BlueEquipmentTemplatesSeeder;
use Database\Seeders\EquipmentSetsSeeder;
use Database\Seeders\GemsSeeder;
use Database\Seeders\ItemsSeeder;

class PlayerEquipmentStatAggregatorTest extends EquipmentTransactionServiceTestCase
{
    public function test_it_combines_base_bonus_special_and_sources_into_fixed_output_shape(): void
    {
        $this->seed([
            ItemsSeeder::class,
            EquipmentSetsSeeder::class,
            GemsSeeder::class,
            BlueEquipmentTemplatesSeeder::class,
        ]);

        $playerId = 10001;

        $weapon = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_20_a001',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
            true
        );
        $armor = $this->createInstance(
            $playerId,
            'eq_inst_set_armor_20_a001',
            'itm_set_zhaoyao_20_armor',
            'set_equipment',
            'armor',
            'set_zhaoyao_20',
            20,
            0,
            3,
            true
        );
        $ring = $this->createInstance(
            $playerId,
            'eq_inst_blue_ring_45_a001',
            'itm_blue_ring_45',
            'blue_equipment',
            'ring_1',
            null,
            null,
            0,
            0,
            true
        );

        $this->createLoadout($playerId, 'main_weapon', (string) $weapon->instance_id);
        $this->createLoadout($playerId, 'armor', (string) $armor->instance_id);
        $this->createLoadout($playerId, 'ring_1', (string) $ring->instance_id);

        $this->createGemSlots((string) $weapon->instance_id, [1], [1 => 'itm_gem_attr_chijinshi_white']);
        $this->createGemSlots((string) $armor->instance_id);
        $this->createGemSlots((string) $ring->instance_id);

        $result = app(PlayerEquipmentStatAggregator::class)->aggregate($playerId);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(['base_stats', 'bonus_stats', 'special_effects', 'sources'], array_keys($result['data']));

        $this->assertSame([
            'CRIT_RATE' => 2.0,
        ], $result['data']['base_stats']);

        $this->assertSame([
            'bonus_melee_atk' => [
                ['value_type' => 'percent', 'value' => 8, 'source' => 'set_zhaoyao_20_2pc'],
                ['value_type' => 'flat', 'value' => 6, 'source' => 'itm_gem_attr_chijinshi_white'],
            ],
        ], $result['data']['bonus_stats']);

        $this->assertSame([], $result['data']['special_effects']);
        $this->assertNotEmpty($result['data']['sources']);
        $this->assertContains(
            ['type' => 'equipment', 'source' => 'eq_inst_set_weapon_20_a001'],
            $result['data']['sources']
        );
        $this->assertContains(
            ['type' => 'set_effect', 'source' => 'set_zhaoyao_20_2pc'],
            $result['data']['sources']
        );
        $this->assertContains(
            ['type' => 'gem', 'source' => 'itm_gem_attr_chijinshi_white'],
            $result['data']['sources']
        );
    }
}
