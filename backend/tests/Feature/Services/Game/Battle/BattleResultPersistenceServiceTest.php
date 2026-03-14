<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Models\BattleResult;
use App\Services\Game\Battle\BattleResultPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleResultPersistenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_victory_can_be_persisted(): void
    {
        $result = app(BattleResultPersistenceService::class)->execute('player_10001', [
            'battle_result' => 'victory',
            'elapsed_ticks' => 12,
            'remaining_player_hp' => 320,
            'remaining_enemy_count' => 0,
            'cleared_wave_count' => 2,
        ], [
            'battle_id' => 'battle_runtime_001',
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame('battle_runtime_001', $result['data']['battle_id']);
        $this->assertSame('victory', $result['data']['battle_result']);
        $this->assertTrue($result['data']['persisted']);
        $this->assertDatabaseHas('battle_results', [
            'battle_id' => 'battle_runtime_001',
            'battle_result' => 'victory',
            'is_settled' => false,
        ]);
    }

    public function test_defeat_can_be_persisted(): void
    {
        $result = app(BattleResultPersistenceService::class)->execute('player_10001', [
            'battle_result' => 'defeat',
            'elapsed_ticks' => 8,
            'remaining_player_hp' => 0,
            'remaining_enemy_count' => 3,
            'cleared_wave_count' => 1,
        ], [
            'battle_id' => 'battle_runtime_002',
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('defeat', $result['data']['battle_result']);
        $this->assertDatabaseHas('battle_results', [
            'battle_id' => 'battle_runtime_002',
            'battle_result' => 'defeat',
        ]);
    }

    public function test_duplicate_battle_id_is_rejected(): void
    {
        BattleResult::query()->create([
            'battle_id' => 'battle_runtime_003',
            'player_id' => 'player_10001',
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
            'battle_result' => 'victory',
            'elapsed_ticks' => 12,
            'remaining_player_hp' => 320,
            'remaining_enemy_count' => 0,
            'cleared_wave_count' => 2,
            'is_settled' => true,
            'settled_at' => now(),
        ]);

        $result = app(BattleResultPersistenceService::class)->execute('player_10001', [
            'battle_result' => 'victory',
        ], [
            'battle_id' => 'battle_runtime_003',
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('battle already settled', $result['reason']);
        $this->assertFalse($result['data']['persisted']);
    }
}
