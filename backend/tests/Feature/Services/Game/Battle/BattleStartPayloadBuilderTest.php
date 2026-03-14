<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Services\Game\Battle\BattleStartPayloadBuilder;
use Database\Seeders\BlueEquipmentTemplatesSeeder;
use Database\Seeders\EquipmentSetsSeeder;
use Database\Seeders\GemsSeeder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Database\Seeders\MonstersSeeder;
use Tests\Feature\Services\Game\Equipment\EquipmentTransactionServiceTestCase;

class BattleStartPayloadBuilderTest extends EquipmentTransactionServiceTestCase
{
    public function test_it_builds_unified_battle_start_payload(): void
    {
        $this->seed([
            ItemsSeeder::class,
            EquipmentSetsSeeder::class,
            GemsSeeder::class,
            BlueEquipmentTemplatesSeeder::class,
            MainStageModuleSeeder::class,
            MonstersSeeder::class,
        ]);

        $playerId = 10001;

        $weapon = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_20_payload',
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
            'eq_inst_set_armor_20_payload',
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

        $result = app(BattleStartPayloadBuilder::class)->build($playerId, [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_01',
            'difficulty_id' => 'stage_01_difficulty_1',
            'scene_id' => 'scene_stage_01',
            'settlement_mode' => 'normal',
            'version' => 'v1',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(
            ['player_snapshot', 'enemy_snapshots', 'battle_context', 'debug_sources'],
            array_keys($result['data'])
        );
        $this->assertSame($playerId, $result['data']['player_snapshot']['player_id']);
        $this->assertCount(5, $result['data']['enemy_snapshots']);
        $this->assertSame('stage_01', $result['data']['battle_context']['stage_id']);
        $this->assertContains(
            ['type' => 'monster_config', 'source' => 'stage_difficulty_monsters'],
            $result['data']['debug_sources']
        );
    }
}
