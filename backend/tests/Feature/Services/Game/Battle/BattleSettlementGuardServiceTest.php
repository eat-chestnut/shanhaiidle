<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Models\BattleResult;
use App\Models\PlayerMainStageFirstClearClaim;
use App\Services\Game\Battle\BattleSettlementGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleSettlementGuardServiceTest extends TestCase
{
    use BattleSettlementTestSupport;
    use RefreshDatabase;

    public function test_unsettled_battle_id_is_allowed(): void
    {
        $result = app(BattleSettlementGuardService::class)->check('player_10001', 'battle_runtime_001', [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertTrue($result['data']['allowed']);
    }

    public function test_settled_battle_id_is_rejected(): void
    {
        BattleResult::query()->create([
            'battle_id' => 'battle_runtime_002',
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

        $result = app(BattleSettlementGuardService::class)->check('player_10001', 'battle_runtime_002', [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('battle already settled', $result['reason']);
        $this->assertFalse($result['data']['allowed']);
    }

    public function test_first_clear_claimed_flag_prevents_repeat_first_clear_reward(): void
    {
        PlayerMainStageFirstClearClaim::query()->create([
            'player_id' => 'player_10001',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
            'battle_result_id' => null,
            'claimed_at' => now(),
        ]);

        $result = app(BattleSettlementGuardService::class)->check('player_10001', 'battle_runtime_003', [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['allowed']);
        $this->assertTrue($result['data']['first_clear_already_claimed']);
    }
}
