<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use App\Models\BlueEquipmentTemplate;
use App\Models\BlueEquipmentTemplateBaseStat;
use App\Models\Item;
use Database\Seeders\BlueEquipmentTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BlueEquipmentTemplateModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_blue_equipment_template_seed_imports_exact_slot_band_rows_and_creates_missing_result_items(): void
    {
        $this->seed([
            BlueEquipmentTemplatesSeeder::class,
        ]);

        $templates = BlueEquipmentTemplate::query()->orderBy('sort_order')->get();

        $this->assertCount(15, $templates);
        $this->assertSame(
            [
                'blue_main_weapon_20',
                'blue_sub_weapon_20',
                'blue_armor_20',
                'blue_leg_20',
                'blue_shoe_20',
                'blue_cloak_20',
                'blue_helmet_20',
                'blue_necklace_20',
                'blue_main_weapon_35',
                'blue_armor_35',
                'blue_bracelet_35',
                'blue_ring_45',
                'blue_main_weapon_50',
                'blue_armor_50',
                'blue_main_weapon_60',
            ],
            $templates->pluck('template_id')->all(),
        );

        $this->assertSame(22, BlueEquipmentTemplateBaseStat::query()->count());
        $this->assertFalse(BlueEquipmentTemplate::query()->where('slot_type', 'talisman')->exists());
        $this->assertFalse(BlueEquipmentTemplate::query()->where('slot_type', 'bracelet')->where('level_band', '<', 35)->exists());
        $this->assertFalse(BlueEquipmentTemplate::query()->where('slot_type', 'ring')->where('level_band', '<', 45)->exists());

        $bracelet = BlueEquipmentTemplate::query()->where('template_id', 'blue_bracelet_35')->firstOrFail();
        $ring = BlueEquipmentTemplate::query()->where('template_id', 'blue_ring_45')->firstOrFail();
        $armor20 = BlueEquipmentTemplate::query()->where('template_id', 'blue_armor_20')->firstOrFail();

        $this->assertSame('itm_blue_bracelet_35', $bracelet->result_item_id);
        $this->assertSame('itm_blue_ring_45', $ring->result_item_id);
        $this->assertCount(2, $armor20->baseStats);
        $this->assertSame(['HP', 'DEF'], $armor20->baseStats->pluck('stat_key')->all());

        $this->assertTrue(Item::query()->where('item_id', 'itm_blue_main_weapon_20')->exists());
        $this->assertTrue(Item::query()->where('item_id', 'itm_blue_ring_45')->exists());
        $this->assertSame('equipment', Item::query()->where('item_id', 'itm_blue_ring_45')->value('main_type'));
        $this->assertSame('blue_equipment', Item::query()->where('item_id', 'itm_blue_ring_45')->value('sub_type'));
        $this->assertFalse(Item::query()->where('item_id', 'itm_blue_ring_35')->exists());
    }

    public function test_blue_equipment_template_export_uses_json_structure_with_rules_and_base_stats(): void
    {
        $this->seed([
            BlueEquipmentTemplatesSeeder::class,
        ]);

        Artisan::call('game:export-blue-equipment-templates');

        $payload = json_decode((string) file_get_contents(storage_path('app/exports/blue_equipment_templates_v1.json')), true);

        $this->assertSame('v1', $payload['version'] ?? null);
        $this->assertSame('blue_equipment_templates', $payload['module'] ?? null);
        $this->assertSame(
            [
                'talisman_excluded' => true,
                'bracelet_min_level_band' => 35,
                'ring_min_level_band' => 45,
                'quality' => 'blue',
                'rarity' => 'blue',
            ],
            $payload['rules'] ?? null,
        );

        $templates = collect($payload['templates'] ?? []);
        $bracelet = $templates->firstWhere('template_id', 'blue_bracelet_35');
        $ring = $templates->firstWhere('template_id', 'blue_ring_45');
        $armor20 = $templates->firstWhere('template_id', 'blue_armor_20');

        $this->assertCount(15, $templates);
        $this->assertNull($templates->firstWhere('slot_type', 'talisman'));
        $this->assertSame('bracelet', $bracelet['slot_type']);
        $this->assertSame(35, $bracelet['level_band']);
        $this->assertSame('ring', $ring['slot_type']);
        $this->assertSame(45, $ring['level_band']);
        $this->assertSame('itm_blue_armor_20', $armor20['result_item_id']);
        $this->assertSame(1, $armor20['blue_affix_count_min']);
        $this->assertSame(2, $armor20['blue_affix_count_max']);
        $this->assertCount(2, $armor20['base_stats']);
        $this->assertSame('HP', $armor20['base_stats'][0]['stat_key']);
        $this->assertSame('DEF', $armor20['base_stats'][1]['stat_key']);
    }

    public function test_config_bundle_registers_blue_equipment_template_export_file(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);
        $this->assertContains('blue_equipment_templates', array_column($files, 'key'));
        $this->assertContains('blue_equipment_templates_v1.json', array_column($files, 'filename'));
    }
}
