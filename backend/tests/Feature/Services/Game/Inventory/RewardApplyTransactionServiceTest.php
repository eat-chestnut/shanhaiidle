<?php

namespace Tests\Feature\Services\Game\Inventory;

use App\Services\Game\Inventory\RewardApplyTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardApplyTransactionServiceTest extends TestCase
{
    use RefreshDatabase;
    use RewardApplyTestSupport;

    public function test_rewards_can_be_applied_in_one_transaction(): void
    {
        $this->createItem('cur_gold', 'currency', 'gold');
        $this->createItem('mat_star_sand_fragment_low', 'material', 'star_material');
        $this->createItem('core_qingqiu_frost', 'boss_core', 'boss_core');

        $result = app(RewardApplyTransactionService::class)->execute(
            '10001',
            'battle_runtime_001',
            [
                'grant_batch_id' => 'grant_batch_001',
                'reward_items' => [
                    ['item_id' => 'cur_gold', 'count' => 500],
                    ['item_id' => 'mat_star_sand_fragment_low', 'count' => 8],
                    ['item_id' => 'core_qingqiu_frost', 'count' => 1],
                ],
                'reward_source_type' => 'battle_settlement',
                'reward_source_id' => 'battle_runtime_001',
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(null, $result['reason']);
        $this->assertSame('battle_runtime_001', $result['data']['battle_id']);
        $this->assertTrue($result['data']['granted']);
        $this->assertDatabaseHas('player_currencies', [
            'player_id' => '10001',
            'currency_id' => 'cur_gold',
            'amount' => 500,
        ]);
        $this->assertDatabaseHas('player_items', [
            'player_id' => '10001',
            'item_id' => 'mat_star_sand_fragment_low',
            'count' => 8,
        ]);
        $this->assertDatabaseHas('player_items', [
            'player_id' => '10001',
            'item_id' => 'core_qingqiu_frost',
            'count' => 1,
        ]);
        $this->assertDatabaseCount('reward_grant_logs', 3);
    }

    public function test_guard_rejection_will_not_grant_anything(): void
    {
        $this->createItem('cur_gold', 'currency', 'gold');
        $this->createRewardGrantLog([
            'battle_id' => 'battle_runtime_001',
            'grant_batch_id' => 'grant_batch_100',
        ]);

        $result = app(RewardApplyTransactionService::class)->execute(
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

        $this->assertFalse($result['ok']);
        $this->assertSame('reward already granted for battle_id battle_runtime_001', $result['reason']);
        $this->assertFalse($result['data']['granted']);
        $this->assertSame([], $result['data']['granted_items']);
        $this->assertDatabaseMissing('player_currencies', [
            'player_id' => '10001',
            'currency_id' => 'cur_gold',
        ]);
        $this->assertDatabaseCount('reward_grant_logs', 1);
    }

    public function test_success_result_contains_granted_items(): void
    {
        $this->createItem('cur_gold', 'currency', 'gold');
        $this->createItem('mat_star_sand_fragment_low', 'material', 'star_material');
        $this->createItem('core_qingqiu_frost', 'boss_core', 'boss_core');

        $result = app(RewardApplyTransactionService::class)->execute(
            '10001',
            'battle_runtime_001',
            [
                'grant_batch_id' => 'grant_batch_001',
                'reward_items' => [
                    ['item_id' => 'cur_gold', 'count' => 500],
                    ['item_id' => 'mat_star_sand_fragment_low', 'count' => 8],
                    ['item_id' => 'core_qingqiu_frost', 'count' => 1],
                ],
                'reward_source_type' => 'battle_settlement',
                'reward_source_id' => 'battle_runtime_001',
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame([
            ['item_id' => 'cur_gold', 'count' => 500],
            ['item_id' => 'mat_star_sand_fragment_low', 'count' => 8],
            ['item_id' => 'core_qingqiu_frost', 'count' => 1],
        ], $result['data']['granted_items']);
    }
}
