<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Models\PlayerMainStageFirstClearClaim;
use App\Models\ShopPlayerProfile;
use App\Services\Game\Battle\RewardResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardResolutionServiceTest extends TestCase
{
    use BattleSettlementTestSupport;
    use RefreshDatabase;

    public function test_main_stage_first_clear_reward_can_be_resolved(): void
    {
        $this->createProfile('player_10001');
        $this->createItem('cur_gold', 'currency');
        $this->createItem('mat_star_sand_fragment_low');
        $this->createMainStageDifficulty();
        $this->createFirstClearReward('hard', 'cur_gold', 500);
        $this->createFirstClearReward('hard', 'mat_star_sand_fragment_low', 8);
        $record = $this->createBattleResultRecord();

        $result = app(RewardResolutionService::class)->execute('player_10001', $record, [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            ['item_id' => 'cur_gold', 'count' => 500],
            ['item_id' => 'mat_star_sand_fragment_low', 'count' => 8],
        ], $result['data']['reward_items']);
        $this->assertTrue($result['data']['first_clear_granted']);
        $this->assertSame([
            ['type' => 'main_stage', 'id' => 'stage_nanshan_04'],
        ], $result['data']['next_unlocks']);
        $this->assertSame('main_stage_first_clear', $result['data']['debug_reward_sources'][0]['type']);

        $profile = ShopPlayerProfile::query()->where('player_id', 'player_10001')->firstOrFail();
        $this->assertSame(500, (int) $profile->gold);
        $this->assertSame(8, (int) ($profile->inventory['mat_star_sand_fragment_low'] ?? 0));
        $this->assertDatabaseHas('player_main_stage_first_clear_claims', [
            'player_id' => 'player_10001',
            'difficulty_id' => 'hard',
        ]);
    }

    public function test_main_stage_non_first_clear_does_not_grant_first_clear_reward_again(): void
    {
        $this->createProfile('player_10001');
        $this->createItem('cur_gold', 'currency');
        $this->createMainStageDifficulty();
        $this->createFirstClearReward('hard', 'cur_gold', 500);
        $record = $this->createBattleResultRecord();

        PlayerMainStageFirstClearClaim::query()->create([
            'player_id' => 'player_10001',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
            'battle_result_id' => null,
            'claimed_at' => now(),
        ]);

        $result = app(RewardResolutionService::class)->execute('player_10001', $record, [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['data']['reward_items']);
        $this->assertFalse($result['data']['first_clear_granted']);
        $this->assertSame([], $result['data']['next_unlocks']);
        $this->assertSame([], $result['data']['debug_reward_sources']);
    }

    public function test_boss_drop_is_resolved_from_monster_drop_config(): void
    {
        $this->createProfile('player_10001');
        $this->createItem('mat_boss_mark_qingqiu');
        $this->createItem('core_qingqiu_frost', 'boss_core');
        $this->createBossWithDrop('mon_qingqiu_boss', 'mat_boss_mark_qingqiu', 1);
        $this->createBossWithDrop('mon_qingqiu_boss_secondary', 'core_qingqiu_frost', 1);
        $record = $this->createBattleResultRecord('battle_runtime_boss', 'player_10001', 'main_stage', 'stage_nanshan_03', 'hard', 'victory');

        $result = app(RewardResolutionService::class)->execute('player_10001', $record, [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
            'defeated_boss_monster_ids' => ['mon_qingqiu_boss', 'mon_qingqiu_boss_secondary'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            ['item_id' => 'core_qingqiu_frost', 'count' => 1],
            ['item_id' => 'mat_boss_mark_qingqiu', 'count' => 1],
        ], $result['data']['reward_items']);
        $this->assertFalse($result['data']['first_clear_granted']);
        $this->assertSame([], $result['data']['next_unlocks']);
        $this->assertSame('monster_drop_config', $result['data']['debug_reward_sources'][0]['type']);
        $this->assertSame('monster_drop_config', $result['data']['debug_reward_sources'][1]['type']);
    }

    public function test_debug_reward_sources_marks_monster_drop_config(): void
    {
        $this->createProfile('player_10001');
        $this->createItem('mat_boss_mark_qingqiu');
        $this->createBossWithDrop();
        $record = $this->createBattleResultRecord('battle_runtime_debug');

        $result = app(RewardResolutionService::class)->execute('player_10001', $record, [
            'battle_type' => 'main_stage',
            'stage_id' => 'stage_nanshan_03',
            'difficulty_id' => 'hard',
            'defeated_boss_monster_ids' => ['mon_qingqiu_boss'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('monster_drop_config', $result['data']['debug_reward_sources'][0]['type']);
        $this->assertSame('mon_qingqiu_boss', $result['data']['debug_reward_sources'][0]['source']);
    }

    public function test_daily_dungeon_does_not_grant_base_reward_in_this_round(): void
    {
        $this->createProfile('player_10001');
        $record = $this->createBattleResultRecord('battle_runtime_daily', 'player_10001', 'daily_dungeon', 'daily_dungeon_fire', 'level_1', 'victory');

        $result = app(RewardResolutionService::class)->execute('player_10001', $record, [
            'battle_type' => 'daily_dungeon',
            'stage_id' => 'daily_dungeon_fire',
            'difficulty_id' => 'level_1',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['data']['reward_items']);
        $this->assertFalse($result['data']['first_clear_granted']);
        $this->assertSame([], $result['data']['next_unlocks']);
        $this->assertSame([], $result['data']['debug_reward_sources']);
    }
}
