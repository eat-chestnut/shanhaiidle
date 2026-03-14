<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Services\Game\Battle\PlayerBattleSnapshotBuilder;
use Database\Seeders\BlueEquipmentTemplatesSeeder;
use Database\Seeders\EquipmentSetsSeeder;
use Database\Seeders\GemsSeeder;
use Database\Seeders\ItemsSeeder;
use Tests\Feature\Services\Game\Equipment\EquipmentTransactionServiceTestCase;

class PlayerBattleSnapshotBuilderTest extends EquipmentTransactionServiceTestCase
{
    public function test_it_builds_player_battle_snapshot_with_fixed_keys(): void
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
            'eq_inst_set_weapon_20_battle',
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
            'eq_inst_set_armor_20_battle',
            'itm_set_zhaoyao_20_armor',
            'set_equipment',
            'armor',
            'set_zhaoyao_20',
            20,
            0,
            3,
            true
        );

        $this->createLoadout($playerId, 'main_weapon', (string) $weapon->instance_id);
        $this->createLoadout($playerId, 'armor', (string) $armor->instance_id);
        $this->createGemSlots((string) $weapon->instance_id, [1], [1 => 'itm_gem_attr_chijinshi_white']);
        $this->createGemSlots((string) $armor->instance_id);

        $result = app(PlayerBattleSnapshotBuilder::class)->build($playerId);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(
            ['player_id', 'base_stats', 'bonus_stats', 'special_effects', 'equipment_summary', 'combat_tags'],
            array_keys($result['data'])
        );
        $this->assertSame($playerId, $result['data']['player_id']);
        $this->assertSame([
            [
                'set_id' => 'set_zhaoyao_20',
                'equipped_count' => 2,
            ],
        ], $result['data']['equipment_summary']['set_counts']);
        $this->assertSame([], $result['data']['equipment_summary']['talisman_star_links']);
        $this->assertSame([], $result['data']['equipment_summary']['equipped_boss_core_ids']);
        $this->assertSame([], $result['data']['combat_tags']);
        $this->assertIsArray($result['data']['base_stats']);
        $this->assertArrayHasKey('bonus_melee_atk', $result['data']['bonus_stats']);
    }
}
