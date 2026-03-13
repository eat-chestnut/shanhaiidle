<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetCraftRecipe;
use Database\Seeders\EquipmentSetsSeeder;
use Database\Seeders\ItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EquipmentSetModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_set_export_contains_four_tiers_and_crafting_chain_rules(): void
    {
        $this->seed([
            ItemsSeeder::class,
            EquipmentSetsSeeder::class,
        ]);

        Artisan::call('game:export-equipment-sets');

        $json = (string) file_get_contents(storage_path('app/exports/equipment_sets.json'));
        $payload = json_decode($json, true);

        $rules = $payload['equipment_set_rules'] ?? [];
        $sets = $payload['equipment_sets'] ?? [];
        $items = $payload['equipment_set_items'] ?? [];
        $effects = $payload['equipment_set_effects'] ?? [];
        $recipes = $payload['equipment_set_craft_recipes'] ?? [];
        $costItems = $payload['equipment_set_recipe_cost_items'] ?? [];

        $this->assertCount(4, $sets);
        $this->assertCount(26, $items);
        $this->assertCount(13, $effects);
        $this->assertCount(26, $recipes);
        $this->assertCount(26, $costItems);

        $this->assertSame([20, 40, 50, 60], $rules['supported_levels']);
        $this->assertSame([2, 4], $rules['thresholds_by_level']['20']);
        $this->assertSame([2, 4, 6], $rules['thresholds_by_level']['40']);
        $this->assertSame([2, 4, 6, 8], $rules['thresholds_by_level']['50']);
        $this->assertSame([2, 4, 6, 8], $rules['thresholds_by_level']['60']);
        $this->assertTrue($rules['craft_only']);
        $this->assertFalse($rules['star_upgrade_supported']);

        $set20 = collect($sets)->firstWhere('set_id', 'set_zhaoyao_20');
        $set60 = collect($sets)->firstWhere('set_id', 'set_zhaoyao_60');
        $recipe20 = collect($recipes)->firstWhere('recipe_id', 'recipe_set_zhaoyao_20_main_weapon');
        $recipe40Helmet = collect($recipes)->firstWhere('recipe_id', 'recipe_set_zhaoyao_40_helmet');
        $recipe50Cloak = collect($recipes)->firstWhere('recipe_id', 'recipe_set_zhaoyao_50_cloak');
        $recipe60Necklace = collect($recipes)->firstWhere('recipe_id', 'recipe_set_zhaoyao_60_necklace');
        $set50Slots = collect($items)->where('set_id', 'set_zhaoyao_50')->pluck('slot_type')->values()->all();

        $this->assertSame(4, $set20['piece_total']);
        $this->assertSame(8, $set60['piece_total']);
        $this->assertSame('', $recipe20['required_base_item_id']);
        $this->assertSame('', $recipe20['required_blueprint_item_id']);
        $this->assertSame('itm_set_zhaoyao_20_helmet_base', $recipe40Helmet['required_base_item_id']);
        $this->assertSame('bp_set_zhaoyao_40', $recipe40Helmet['required_blueprint_item_id']);
        $this->assertSame('itm_set_zhaoyao_40_cloak_base', $recipe50Cloak['required_base_item_id']);
        $this->assertSame('bp_set_zhaoyao_50', $recipe50Cloak['required_blueprint_item_id']);
        $this->assertSame('itm_set_zhaoyao_50_necklace', $recipe60Necklace['required_base_item_id']);
        $this->assertSame('bp_set_zhaoyao_60', $recipe60Necklace['required_blueprint_item_id']);
        $this->assertSame(
            ['main_weapon', 'sub_weapon', 'armor', 'shoe', 'helmet', 'leg', 'cloak', 'necklace'],
            $set50Slots
        );
    }

    public function test_equipment_set_seed_links_items_effects_and_recipes_to_main_tables(): void
    {
        $this->seed([
            ItemsSeeder::class,
            EquipmentSetsSeeder::class,
        ]);

        $set40 = EquipmentSet::query()->where('set_id', 'set_zhaoyao_40')->firstOrFail();
        $set60 = EquipmentSet::query()->where('set_id', 'set_zhaoyao_60')->firstOrFail();
        $recipe40Weapon = EquipmentSetCraftRecipe::query()
            ->where('recipe_id', 'recipe_set_zhaoyao_40_main_weapon')
            ->firstOrFail();
        $recipe50Cloak = EquipmentSetCraftRecipe::query()
            ->where('recipe_id', 'recipe_set_zhaoyao_50_cloak')
            ->firstOrFail();

        $this->assertCount(6, $set40->items);
        $this->assertCount(3, $set40->effects);
        $this->assertCount(6, $set40->recipes);
        $this->assertCount(8, $set60->items);
        $this->assertCount(4, $set60->effects);
        $this->assertCount(8, $set60->recipes);

        $this->assertSame('itm_set_zhaoyao_20_main_weapon', $recipe40Weapon->required_base_item_id);
        $this->assertSame('bp_set_zhaoyao_40', $recipe40Weapon->required_blueprint_item_id);
        $this->assertSame('itm_set_zhaoyao_40_cloak_base', $recipe50Cloak->required_base_item_id);
        $this->assertCount(1, $recipe50Cloak->costItems);
        $this->assertSame('招摇印记', $recipe50Cloak->costItems->first()?->item_id);
    }

    public function test_config_bundle_registers_equipment_set_export_file(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);
        $this->assertContains('equipment_sets', array_column($files, 'key'));
        $this->assertContains('equipment_sets.json', array_column($files, 'filename'));
    }
}
