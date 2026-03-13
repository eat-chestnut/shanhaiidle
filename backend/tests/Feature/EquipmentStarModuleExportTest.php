<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use App\Models\EquipmentStarUpgradeCost;
use App\Models\Item;
use App\Services\EquipmentStarModuleImportService;
use Database\Seeders\ItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EquipmentStarModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_star_export_contains_stage_caps_costs_slot_unlocks_and_progression_rules(): void
    {
        $this->seed([
            ItemsSeeder::class,
        ]);
        $this->seedRequiredStarItems();

        EquipmentStarModuleImportService::importFromProjectFile();

        Artisan::call('game:export-equipment-star-module');

        $json = (string) file_get_contents(storage_path('app/exports/equipment_star_module_v1.json'));
        $payload = json_decode($json, true);

        $this->assertSame('v1', $payload['version']);
        $this->assertSame('equipment_star_module', $payload['module']);
        $this->assertSame([
            'star_mode' => 'always_success',
            'failure_supported' => false,
            'downgrade_supported' => false,
            'guarantee_supported' => false,
            'progression_requires_max_star' => true,
            'progression_keeps_current_star' => true,
            'core_material_source' => 'star_sand_dungeon',
        ], $payload['rules']);

        $this->assertSame([
            20 => 3,
            40 => 6,
            50 => 8,
            60 => 10,
        ], collect($payload['star_rules'])->pluck('max_star', 'set_level')->all());

        $this->assertSame([
            3 => ['slot_index' => 1, 'slot_group' => 'attr_only'],
            6 => ['slot_index' => 2, 'slot_group' => 'attr_only'],
            8 => ['slot_index' => 3, 'slot_group' => 'skill_only'],
            10 => ['slot_index' => 4, 'slot_group' => 'skill_only'],
        ], collect($payload['slot_unlocks'])->mapWithKeys(fn (array $row): array => [
            $row['required_star'] => [
                'slot_index' => $row['slot_index'],
                'slot_group' => $row['slot_group'],
            ],
        ])->all());

        $this->assertSame([
            20 => ['to_set_level' => 40, 'required_max_star' => 3, 'star_keep_mode' => 'keep_current_star'],
            40 => ['to_set_level' => 50, 'required_max_star' => 6, 'star_keep_mode' => 'keep_current_star'],
            50 => ['to_set_level' => 60, 'required_max_star' => 8, 'star_keep_mode' => 'keep_current_star'],
        ], collect($payload['progression_rules'])->mapWithKeys(fn (array $row): array => [
            $row['from_set_level'] => [
                'to_set_level' => $row['to_set_level'],
                'required_max_star' => $row['required_max_star'],
                'star_keep_mode' => $row['star_keep_mode'],
            ],
        ])->all());

        $upgradeCosts = $payload['upgrade_costs'] ?? [];
        $this->assertCount(7, $upgradeCosts);
        $this->assertSame([20, 20, 20, 40, 40, 50, 60], array_column($upgradeCosts, 'set_level'));

        $maxTransition = collect($upgradeCosts)->firstWhere('to_star', 10);
        $this->assertSame(60, $maxTransition['set_level']);
        $this->assertSame(['mat_star_sand_high', 'mat_star_core_extreme', 'cur_gold'], array_column($maxTransition['cost_items'], 'item_id'));
        $this->assertSame([12, 2, 30000], array_column($maxTransition['cost_items'], 'count'));
    }

    public function test_upgrade_cost_items_link_to_items_item_id(): void
    {
        $this->seed([
            ItemsSeeder::class,
        ]);
        $this->seedRequiredStarItems();

        EquipmentStarModuleImportService::importFromProjectFile();

        $cost = EquipmentStarUpgradeCost::query()
            ->where('set_level', 60)
            ->where('from_star', 9)
            ->where('to_star', 10)
            ->where('item_id', 'mat_star_core_extreme')
            ->firstOrFail();

        $this->assertNotNull($cost->item);
        $this->assertSame('mat_star_core_extreme', $cost->item->item_id);
    }

    public function test_config_bundle_registers_equipment_star_module_file(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);
        $this->assertContains('equipment_star_module', array_column($files, 'key'));
        $this->assertContains('equipment_star_module_v1.json', array_column($files, 'filename'));
    }

    private function seedRequiredStarItems(): void
    {
        $rows = [
            $this->materialItem('mat_star_sand_fragment_low', '低阶星砂碎片', 910010),
            $this->materialItem('mat_star_sand_low', '低阶星砂', 910020),
            $this->materialItem('mat_star_sand_fragment_mid', '中阶星砂碎片', 910030),
            $this->materialItem('mat_star_sand_mid', '中阶星砂', 910040),
            $this->materialItem('mat_star_sand_high_fragment', '高阶星砂碎片', 910050),
            $this->materialItem('mat_star_sand_high', '高阶星砂', 910060),
            $this->materialItem('mat_star_core_extreme', '极星核心', 910070, 'red'),
        ];

        foreach ($rows as $row) {
            Item::query()->updateOrCreate(
                ['item_id' => $row['item_id']],
                $row,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function materialItem(string $itemId, string $name, int $sortOrder, string $quality = 'purple'): array
    {
        return [
            'id' => $itemId,
            'item_id' => $itemId,
            'item_name' => $name,
            'display_name' => $name,
            'main_type' => 'material',
            'sub_type' => 'star_material',
            'quality' => $quality,
            'rarity' => $quality,
            'icon' => null,
            'desc' => '套装升星材料。',
            'is_stackable' => true,
            'max_stack' => 9999,
            'is_enabled' => true,
            'sort_order' => $sortOrder,
            'remark' => null,
            'source_library' => 'equipment_star_module',
            'required_level' => 1,
            'bind_type' => 'none',
            'sell_price' => 0,
            'use_type' => 'craft_material',
            'rarity_frame_key' => null,
            'name' => $name,
            'type' => 'material',
            'material_type' => 'star',
            'trait' => null,
            'effect_type' => null,
            'target_scope' => null,
            'effect_payload' => null,
            'drop_unlock_level' => 1,
            'socket_limit' => [],
            'source_tags' => ['equipment_star_module', 'star_sand_dungeon'],
            'use_tags' => ['equipment_star_upgrade'],
            'stack_limit' => 9999,
            'can_compose' => false,
            'can_reforge' => false,
        ];
    }
}
