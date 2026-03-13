<?php

namespace Tests\Feature;

use App\Models\PlayerMilestone;
use App\Models\ShopPlayerProfile;
use App\Services\MilestonePlayerService;
use Database\Seeders\GiftPackModuleSeeder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Database\Seeders\MilestoneModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestonePlayerStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_milestones_support_three_states_and_one_time_claim(): void
    {
        $this->seed([
            ItemsSeeder::class,
            GiftPackModuleSeeder::class,
            MainStageModuleSeeder::class,
            MilestoneModuleSeeder::class,
        ]);

        ShopPlayerProfile::query()->create([
            'player_id' => 'player_milestone_test',
            'level' => 10,
            'exp' => 0,
            'gold' => 100,
            'crystal' => 0,
            'contribution' => 0,
            'highest_cleared_stage_id' => 'stage_03',
            'highest_cleared_difficulty' => 1,
            'free_attr_points' => 0,
            'skill_points' => 0,
            'inventory' => [],
            'equipment' => [],
            'claimed_milestones' => [],
        ]);

        $service = app(MilestonePlayerService::class);
        $summary = $service->syncForPlayer('player_milestone_test');

        $this->assertSame(6, $summary['milestone_total']);
        $this->assertSame(4, $summary['milestone_unlocked']);
        $this->assertSame(4, $summary['milestone_claimable']);
        $this->assertSame(0, $summary['claimed_milestone_count']);

        $this->assertDatabaseHas('player_milestones', [
            'player_id' => 'player_milestone_test',
            'milestone_id' => 'ms_lv_05',
            'is_unlocked' => true,
            'is_claimed' => false,
        ]);
        $this->assertDatabaseHas('player_milestones', [
            'player_id' => 'player_milestone_test',
            'milestone_id' => 'ms_lv_10',
            'is_unlocked' => true,
            'is_claimed' => false,
        ]);
        $this->assertDatabaseHas('player_milestones', [
            'player_id' => 'player_milestone_test',
            'milestone_id' => 'ms_stage_03_clear',
            'is_unlocked' => true,
            'is_claimed' => false,
        ]);
        $this->assertDatabaseHas('player_milestones', [
            'player_id' => 'player_milestone_test',
            'milestone_id' => 'ms_lv_20',
            'is_unlocked' => false,
            'is_claimed' => false,
        ]);
        $this->assertDatabaseHas('player_milestones', [
            'player_id' => 'player_milestone_test',
            'milestone_id' => 'ms_stage_08_clear',
            'is_unlocked' => false,
            'is_claimed' => false,
        ]);

        $claimResult = $service->claim('player_milestone_test', 'ms_stage_03_clear');
        $repeatClaimResult = $service->claim('player_milestone_test', 'ms_stage_03_clear');

        $this->assertTrue($claimResult['ok']);
        $this->assertSame('cur_premium_jade', $claimResult['reward_item_id']);
        $this->assertSame(30, $claimResult['reward_count']);
        $this->assertSame(30, $claimResult['updated_currencies']['crystal']);

        $profile = ShopPlayerProfile::query()->where('player_id', 'player_milestone_test')->firstOrFail();
        $this->assertSame(30, (int) $profile->crystal);
        $this->assertContains('ms_stage_03_clear', $profile->claimed_milestones ?? []);

        /** @var PlayerMilestone $claimedState */
        $claimedState = PlayerMilestone::query()
            ->where('player_id', 'player_milestone_test')
            ->where('milestone_id', 'ms_stage_03_clear')
            ->firstOrFail();
        $this->assertTrue((bool) $claimedState->is_unlocked);
        $this->assertTrue((bool) $claimedState->is_claimed);
        $this->assertNotNull($claimedState->claimed_at);

        $this->assertFalse($repeatClaimResult['ok']);
        $this->assertSame('milestone_already_claimed', $repeatClaimResult['reason']);
    }
}
