<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use App\Models\BossCore;
use Database\Seeders\BossCoresSeeder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Database\Seeders\MonstersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BossCoreModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_boss_core_export_contains_standalone_itemized_cores(): void
    {
        $this->seed([
            ItemsSeeder::class,
            MainStageModuleSeeder::class,
            MonstersSeeder::class,
            BossCoresSeeder::class,
        ]);

        Artisan::call('game:export-boss-core-module');

        $json = (string) file_get_contents(storage_path('app/exports/boss_core_module_v1.json'));
        $payload = json_decode($json, true);

        $rules = $payload['boss_core_rules'] ?? [];
        $cores = $payload['boss_cores'] ?? [];
        $effects = $payload['boss_core_effects'] ?? [];

        $this->assertCount(2, $cores);
        $this->assertCount(2, $effects);
        $this->assertContains('Boss 核心独立于宝石模块。', $rules['module_boundary']);
        $this->assertContains('Boss 核心独立于套装件数效果，不占套装效果栏。', $rules['module_boundary']);

        $qingqiu = collect($cores)->firstWhere('core_id', 'core_qingqiu_frost');
        $zhaoyao = collect($cores)->firstWhere('core_id', 'core_zhaoyao_burst');
        $qingqiuEffect = collect($effects)->firstWhere('core_id', 'core_qingqiu_frost');
        $zhaoyaoEffect = collect($effects)->firstWhere('core_id', 'core_zhaoyao_burst');

        $this->assertSame('core_qingqiu_frost', $qingqiu['item_id']);
        $this->assertSame('fulu', $qingqiu['recommended_sect']);
        $this->assertSame('frost', $qingqiu['recommended_build']);
        $this->assertSame('gold', $qingqiu['quality']);
        $this->assertSame('bonus_frost_damage', $qingqiuEffect['effect_key']);
        $this->assertSame('percent', $qingqiuEffect['value_type']);
        $this->assertSame(12, $qingqiuEffect['value']);

        $this->assertSame('bing', $zhaoyao['recommended_sect']);
        $this->assertSame('burst', $zhaoyao['recommended_build']);
        $this->assertSame('bonus_melee_atk', $zhaoyaoEffect['effect_key']);
        $this->assertSame(10, $zhaoyaoEffect['value']);
    }

    public function test_boss_core_seed_links_to_item_anchor_boss_source_and_single_effect(): void
    {
        $this->seed([
            ItemsSeeder::class,
            MainStageModuleSeeder::class,
            MonstersSeeder::class,
            BossCoresSeeder::class,
        ]);

        $core = BossCore::query()->where('core_id', 'core_qingqiu_frost')->firstOrFail();

        $this->assertNotNull($core->item);
        $this->assertSame('boss_core', $core->item?->main_type);
        $this->assertSame('boss_core', $core->item?->sub_type);
        $this->assertNotNull($core->sourceBoss);
        $this->assertSame('boss', $core->sourceBoss?->monster_type);
        $this->assertCount(1, $core->effects);
        $this->assertSame('bonus_frost_damage', $core->effects->first()?->effect_key);
    }

    public function test_config_bundle_registers_boss_core_export_file(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);
        $this->assertContains('boss_core_module', array_column($files, 'key'));
        $this->assertContains('boss_core_module_v1.json', array_column($files, 'filename'));
    }
}
