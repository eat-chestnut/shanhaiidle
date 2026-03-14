<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Services\Game\Battle\BattleContextBuilder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_battle_context_from_stage_context_and_main_stage_data(): void
    {
        $this->seed([
            ItemsSeeder::class,
            MainStageModuleSeeder::class,
        ]);

        $result = app(BattleContextBuilder::class)->build([
            'battle_type' => 'main_stage',
            'difficulty_id' => 'stage_01_difficulty_3',
            'scene_id' => 'scene_nanshan_forest',
            'settlement_mode' => 'normal',
            'version' => 'v1',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_01',
            'difficulty_id' => 'stage_01_difficulty_3',
            'recommended_power' => 100,
            'is_boss_battle' => false,
            'scene_id' => 'scene_nanshan_forest',
            'settlement_mode' => 'normal',
            'version' => 'v1',
        ], $result['data']);
    }
}
