<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Services\Game\Battle\EnemyBattleSnapshotBuilder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Database\Seeders\MonstersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnemyBattleSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expands_stage_monsters_into_wave_snapshots(): void
    {
        $this->seed([
            ItemsSeeder::class,
            MainStageModuleSeeder::class,
            MonstersSeeder::class,
        ]);

        $result = app(EnemyBattleSnapshotBuilder::class)->build([
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_01',
            'difficulty_id' => 'stage_01_difficulty_1',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertCount(5, $result['data']);
        $this->assertSame('stage_01_normal_a', $result['data'][0]['monster_id']);
        $this->assertSame(1, $result['data'][0]['wave_index']);
        $this->assertSame(1, $result['data'][0]['unit_index']);
        $this->assertFalse($result['data'][0]['is_boss']);
        $this->assertSame('stage_01_boss', $result['data'][4]['monster_id']);
        $this->assertSame(3, $result['data'][4]['wave_index']);
        $this->assertSame(1, $result['data'][4]['unit_index']);
        $this->assertTrue($result['data'][4]['is_boss']);
        $this->assertSame(['monster_basic_claw'], $result['data'][0]['skills']);
    }
}
