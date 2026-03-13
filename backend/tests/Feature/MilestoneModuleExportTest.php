<?php

namespace Tests\Feature;

use Database\Seeders\GiftPackModuleSeeder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Database\Seeders\MilestoneModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MilestoneModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestone_export_uses_item_based_rewards_and_bundle_key(): void
    {
        $this->seed([
            ItemsSeeder::class,
            GiftPackModuleSeeder::class,
            MainStageModuleSeeder::class,
            MilestoneModuleSeeder::class,
        ]);

        Artisan::call('game:export-progression-milestones');

        $json = (string) file_get_contents(storage_path('app/exports/progression_milestones_v1.json'));
        $payload = json_decode($json, true);
        $rows = $payload['milestones'] ?? [];

        $this->assertCount(6, $rows);
        $this->assertSame([
            'ms_lv_05',
            'ms_lv_10',
            'ms_lv_20',
            'ms_stage_01_clear',
            'ms_stage_03_clear',
            'ms_stage_08_clear',
        ], array_column($rows, 'milestone_id'));

        $this->assertSame([
            'player_level_reached',
            'chapter_cleared',
        ], array_values(array_unique(array_column($rows, 'condition_type'))));
        $this->assertStringNotContainsString('unlock_contents', $json);
        $this->assertStringNotContainsString('player_milestones', $json);

        $level20 = collect($rows)->firstWhere('milestone_id', 'ms_lv_20');
        $stage03 = collect($rows)->firstWhere('milestone_id', 'ms_stage_03_clear');

        $this->assertSame('ms_lv_10', $level20['pre_milestone_id']);
        $this->assertSame('gift_lv20_growth', $level20['reward_item_id']);
        $this->assertSame('ms_stage_01_clear', $stage03['pre_milestone_id']);
        $this->assertSame('cur_premium_jade', $stage03['reward_item_id']);
    }
}
