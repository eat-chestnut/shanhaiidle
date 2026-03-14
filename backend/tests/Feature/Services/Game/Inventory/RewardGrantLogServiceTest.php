<?php

namespace Tests\Feature\Services\Game\Inventory;

use App\Services\Game\Inventory\RewardGrantLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardGrantLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reward_grant_logs_can_be_recorded(): void
    {
        $service = app(RewardGrantLogService::class);

        $result = $service->record(
            '10001',
            'battle_runtime_001',
            [
                ['item_id' => 'cur_gold', 'count' => 500],
                ['item_id' => 'mat_star_sand_fragment_low', 'count' => 8],
            ],
            'battle_settlement',
            'battle_runtime_001',
            'grant_batch_001',
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(null, $result['reason']);
        $this->assertSame(2, $result['data']['logged_count']);
        $this->assertDatabaseCount('reward_grant_logs', 2);
    }

    public function test_reward_grant_log_fields_are_complete(): void
    {
        $service = app(RewardGrantLogService::class);

        $result = $service->record(
            '10001',
            'battle_runtime_001',
            [
                [
                    'item_id' => 'core_qingqiu_frost',
                    'count' => 1,
                    'reward_source_type' => 'monster_drop_config',
                    'reward_source_id' => 'mon_qingqiu_boss',
                ],
            ],
            'battle_settlement',
            'battle_runtime_001',
            'grant_batch_001',
        );

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('reward_grant_logs', [
            'player_id' => '10001',
            'battle_id' => 'battle_runtime_001',
            'item_id' => 'core_qingqiu_frost',
            'count' => 1,
            'reward_source_type' => 'monster_drop_config',
            'reward_source_id' => 'mon_qingqiu_boss',
            'grant_batch_id' => 'grant_batch_001',
        ]);
    }
}
