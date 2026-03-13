<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use App\Models\Talisman;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\TalismansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TalismanModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_talisman_export_contains_tiers_upgrade_costs_and_star_links(): void
    {
        $this->seed([
            ItemsSeeder::class,
            TalismansSeeder::class,
        ]);

        Artisan::call('game:export-talisman-module');

        $json = (string) file_get_contents(storage_path('app/exports/talisman_module_v1.json'));
        $payload = json_decode($json, true);
        $module = $payload['talisman_module'] ?? [];

        $talismans = $module['talismans'] ?? [];
        $tiers = $module['talisman_tiers'] ?? [];
        $upgradeCosts = $module['talisman_tier_upgrade_costs'] ?? [];
        $starLinks = $module['talisman_star_links'] ?? [];
        $rules = $module['rules'] ?? [];

        $this->assertCount(4, $talismans);
        $this->assertCount(12, $tiers);
        $this->assertCount(16, $upgradeCosts);
        $this->assertCount(48, $starLinks);

        $this->assertSame([1, 2, 3], $rules['fixed_tier_numbers']);
        $this->assertSame([6, 8, 9, 10], $rules['star_link_required_equipment_stars']);
        $this->assertSame('all_tracked_slots_min_star', $rules['star_link_condition_mode']);
        $this->assertSame(
            '护符星级连锁所要求的“全身装备达到 X 星”，指所有参与统计的装备位都至少达到该星级。护符位本身不参与该统计。',
            $rules['star_link_condition_summary'],
        );
        $this->assertContains('talisman', $rules['star_link_excluded_slot_ids']);
        $this->assertNotContains('talisman', $rules['star_link_tracked_slot_ids']);

        $common = collect($talismans)->firstWhere('talisman_id', 'tal_common_guard_40');
        $commonTiers = collect($tiers)->where('talisman_id', 'tal_common_guard_40')->values()->all();
        $commonCosts = collect($upgradeCosts)
            ->where('talisman_id', 'tal_common_guard_40')
            ->where('tier_no', 1)
            ->where('target_tier_no', 2)
            ->values()
            ->all();
        $commonLinks = collect($starLinks)
            ->where('talisman_id', 'tal_common_guard_40')
            ->where('tier_no', 1)
            ->values()
            ->all();

        $this->assertSame('itm_talisman_common_40', $common['item_id']);
        $this->assertSame('common_talisman', $common['talisman_type']);
        $this->assertSame('none', $common['recommended_sect']);
        $this->assertSame('blue', $common['quality']);
        $this->assertSame(40, $common['unlock_level']);

        $this->assertSame([1, 2, 3], array_column($commonTiers, 'tier_no'));
        $this->assertSame(['bonus_hp', 'bonus_hp', 'bonus_hp'], array_column($commonTiers, 'effect_key'));
        $this->assertSame([120, 180, 260], array_column($commonTiers, 'value'));

        $this->assertCount(2, $commonCosts);
        $this->assertSame(['mat_talisman_paper_white', 'mat_talisman_ink_blue'], array_column($commonCosts, 'item_id'));
        $this->assertSame([6, 2], array_column($commonCosts, 'count'));

        $this->assertCount(4, $commonLinks);
        $this->assertSame([6, 8, 9, 10], array_column($commonLinks, 'required_equipment_star'));
        $this->assertSame([3, 5, 7, 10], array_column($commonLinks, 'value'));
    }

    public function test_talisman_seed_links_config_to_item_anchor(): void
    {
        $this->seed([
            ItemsSeeder::class,
            TalismansSeeder::class,
        ]);

        $talisman = Talisman::query()->where('talisman_id', 'tal_bing_attack_40')->firstOrFail();

        $this->assertSame('itm_talisman_bing_40', $talisman->item_id);
        $this->assertNotNull($talisman->item);
        $this->assertSame('talisman', $talisman->item->main_type);
        $this->assertSame('sect_talisman', $talisman->item->sub_type);
        $this->assertCount(3, $talisman->tiers);
        $this->assertCount(4, $talisman->upgradeCosts);
        $this->assertCount(12, $talisman->starLinks);
    }

    public function test_config_bundle_registers_talisman_export_file(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);
        $this->assertContains('talisman_module', array_column($files, 'key'));
        $this->assertContains('talisman_module_v1.json', array_column($files, 'filename'));
    }
}
