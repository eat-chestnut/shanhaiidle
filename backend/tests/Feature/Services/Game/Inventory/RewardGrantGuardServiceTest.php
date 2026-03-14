<?php

namespace Tests\Feature\Services\Game\Inventory;

use App\Models\PlayerMainStageFirstClearClaim;
use App\Services\Game\Inventory\RewardGrantGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardGrantGuardServiceTest extends TestCase
{
    use RefreshDatabase;
    use RewardApplyTestSupport;

    public function test_ungranted_battle_id_is_allowed(): void
    {
        $result = app(RewardGrantGuardService::class)->check(
            '10001',
            'battle_runtime_001',
            [
                'grant_batch_id' => 'grant_batch_001',
                'reward_items' => [
                    ['item_id' => 'cur_gold', 'count' => 500],
                ],
                'reward_source_type' => 'battle_settlement',
                'reward_source_id' => 'battle_runtime_001',
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(null, $result['reason']);
        $this->assertSame(['allowed' => true], $result['data']);
    }

    public function test_granted_battle_id_is_rejected(): void
    {
        $this->createRewardGrantLog([
            'battle_id' => 'battle_runtime_001',
            'grant_batch_id' => 'grant_batch_001',
        ]);

        $result = app(RewardGrantGuardService::class)->check(
            '10001',
            'battle_runtime_001',
            [
                'grant_batch_id' => 'grant_batch_002',
                'reward_items' => [
                    ['item_id' => 'cur_gold', 'count' => 500],
                ],
                'reward_source_type' => 'battle_settlement',
                'reward_source_id' => 'battle_runtime_001',
            ],
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('reward already granted for battle_id battle_runtime_001', $result['reason']);
        $this->assertSame(['allowed' => false], $result['data']);
    }

    public function test_duplicate_first_clear_reward_is_rejected(): void
    {
        $this->createRewardGrantLog([
            'player_id' => '10001',
            'battle_id' => 'battle_runtime_100',
            'item_id' => 'mat_star_sand_fragment_low',
            'count' => 8,
            'reward_source_type' => 'main_stage_first_clear',
            'reward_source_id' => 'stage_nanshan_03_hard_first_clear',
            'grant_batch_id' => 'grant_batch_100',
        ]);

        PlayerMainStageFirstClearClaim::query()->create([
            'player_id' => '10001',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
            'battle_result_id' => null,
            'claimed_at' => now(),
        ]);

        $result = app(RewardGrantGuardService::class)->check(
            '10001',
            'battle_runtime_001',
            [
                'grant_batch_id' => 'grant_batch_001',
                'reward_items' => [
                    [
                        'item_id' => 'mat_star_sand_fragment_low',
                        'count' => 8,
                        'reward_source_type' => 'main_stage_first_clear',
                        'reward_source_id' => 'stage_nanshan_03_hard_first_clear',
                    ],
                ],
                'reward_source_type' => 'main_stage_first_clear',
                'reward_source_id' => 'stage_nanshan_03_hard_first_clear',
                'first_clear_claim' => [
                    'difficulty_id' => 'hard',
                ],
            ],
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('first clear reward already granted for source stage_nanshan_03_hard_first_clear', $result['reason']);
        $this->assertSame(['allowed' => false], $result['data']);
    }
}
