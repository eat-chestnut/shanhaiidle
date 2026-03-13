<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use App\Models\Gem;
use Database\Seeders\GemsSeeder;
use Database\Seeders\ItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GemModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_gem_export_uses_item_linked_configs_and_slot_groups(): void
    {
        $this->seed([
            ItemsSeeder::class,
            GemsSeeder::class,
        ]);

        Artisan::call('game:export-gem-catalog');

        $json = (string) file_get_contents(storage_path('app/exports/gem_catalog_v1.json'));
        $payload = json_decode($json, true);
        $rows = $payload['gem_catalog'] ?? [];

        $this->assertCount(5, $rows);
        $this->assertSame([
            'gem_attr_attack_white_01',
            'gem_attr_hp_blue_01',
            'gem_attr_speed_purple_01',
            'gem_skill_frost_blue_01',
            'gem_skill_flame_gold_01',
        ], array_column($rows, 'gem_id'));

        $this->assertStringNotContainsString('effect_type', $json);
        $this->assertStringNotContainsString('target_scope', $json);
        $this->assertStringNotContainsString('socket_limit', $json);
        $this->assertStringNotContainsString('can_compose', $json);
        $this->assertStringNotContainsString('can_reforge', $json);

        $attackGem = collect($rows)->firstWhere('gem_id', 'gem_attr_attack_white_01');
        $flameGem = collect($rows)->firstWhere('gem_id', 'gem_skill_flame_gold_01');

        $this->assertSame([
            'item_id' => 'itm_gem_attr_chijinshi_white',
            'gem_name' => '赤金石',
            'display_name' => '赤金石',
            'gem_type' => 'attr_gem',
            'stat_key' => 'MELEE_ATK',
            'value_type' => 'flat',
            'value' => 6,
            'quality' => 'white',
            'rarity' => 'white',
            'slot_group' => 'attr_only',
            'unlock_level' => 23,
            'is_enabled' => true,
        ], [
            'item_id' => $attackGem['item_id'],
            'gem_name' => $attackGem['gem_name'],
            'display_name' => $attackGem['display_name'],
            'gem_type' => $attackGem['gem_type'],
            'stat_key' => $attackGem['stat_key'],
            'value_type' => $attackGem['value_type'],
            'value' => $attackGem['value'],
            'quality' => $attackGem['quality'],
            'rarity' => $attackGem['rarity'],
            'slot_group' => $attackGem['slot_group'],
            'unlock_level' => $attackGem['unlock_level'],
            'is_enabled' => $attackGem['is_enabled'],
        ]);

        $this->assertSame('itm_gem_skill_chiyuyin_gold', $flameGem['item_id']);
        $this->assertSame('skill_gem', $flameGem['gem_type']);
        $this->assertSame('skill_flame_focus', $flameGem['stat_key']);
        $this->assertSame('percent', $flameGem['value_type']);
        $this->assertSame(18, $flameGem['value']);
        $this->assertSame('gold', $flameGem['quality']);
        $this->assertSame('skill_only', $flameGem['slot_group']);
    }

    public function test_gem_seed_links_configs_to_item_anchor(): void
    {
        $this->seed([
            ItemsSeeder::class,
            GemsSeeder::class,
        ]);

        $gem = Gem::query()->where('gem_id', 'gem_skill_frost_blue_01')->firstOrFail();

        $this->assertSame('itm_gem_skill_qingqiuyin_blue', $gem->item_id);
        $this->assertNotNull($gem->item);
        $this->assertSame('gem', $gem->item->main_type);
        $this->assertSame('skill_gem', $gem->item->sub_type);
    }

    public function test_config_bundle_still_registers_gem_export_file(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);
        $this->assertContains('gem_catalog', array_column($files, 'key'));
        $this->assertContains('gem_catalog_v1.json', array_column($files, 'filename'));
    }
}
