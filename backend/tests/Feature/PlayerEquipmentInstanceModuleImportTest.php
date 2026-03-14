<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use App\Models\PlayerEquipmentLoadout;
use App\Services\PlayerEquipmentInstanceImportService;
use App\Services\PlayerEquipmentLoadoutStatsService;
use Database\Seeders\ItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlayerEquipmentInstanceModuleImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_service_uses_json_examples_and_builds_three_instance_tables(): void
    {
        $this->seed([ItemsSeeder::class]);

        $result = PlayerEquipmentInstanceImportService::importFromProjectFile();

        $this->assertSame([
            'instance_count' => 4,
            'loadout_count' => 4,
            'gem_slot_count' => 4,
        ], $result);

        $this->assertDatabaseCount('player_equipment_instances', 4);
        $this->assertDatabaseCount('player_equipment_loadouts', 4);
        $this->assertDatabaseCount('player_equipment_gem_slots', 4);

        $this->assertSame([
            'main_weapon',
            'armor',
            'ring_1',
            'talisman',
        ], PlayerEquipmentLoadout::query()->orderBy('id')->pluck('slot_type')->all());

        $this->assertSame(
            [
                1 => ['required_star' => 3, 'slot_group' => 'attr_only'],
                2 => ['required_star' => 6, 'slot_group' => 'attr_only'],
                3 => ['required_star' => 8, 'slot_group' => 'skill_only'],
                4 => ['required_star' => 10, 'slot_group' => 'skill_only'],
            ],
            PlayerEquipmentGemSlot::query()
                ->orderBy('slot_index')
                ->get()
                ->mapWithKeys(fn (PlayerEquipmentGemSlot $row): array => [
                    (int) $row->slot_index => [
                        'required_star' => (int) $row->required_star,
                        'slot_group' => (string) $row->slot_group,
                    ],
                ])
                ->all()
        );
    }

    public function test_loadout_and_stats_layer_exclude_talisman_from_set_count_and_star_tracking(): void
    {
        $this->seed([ItemsSeeder::class]);

        $this->upsertItem('itm_set_weapon_test_20', '测试套装主武器', 'equipment', 'set_equipment', 'purple');
        $this->upsertItem('itm_set_ring_test_20', '测试套装戒指', 'equipment', 'set_equipment', 'purple');
        $this->upsertItem('itm_set_talisman_test_20', '测试套装护符', 'equipment', 'set_equipment', 'purple');

        $weapon = PlayerEquipmentInstance::query()->create([
            'player_id' => 10001,
            'instance_id' => 'eq_inst_test_weapon',
            'item_id' => 'itm_set_weapon_test_20',
            'equipment_source_type' => 'set_equipment',
            'slot_type' => 'main_weapon',
            'set_id' => 'set_test_20',
            'set_level' => 20,
            'star' => 3,
            'max_star' => 3,
            'quality' => 'purple',
            'rarity' => 'purple',
            'is_locked' => false,
            'is_equipped' => true,
            'obtained_at' => '2026-03-14 10:00:00',
        ]);

        $ring = PlayerEquipmentInstance::query()->create([
            'player_id' => 10001,
            'instance_id' => 'eq_inst_test_ring_1',
            'item_id' => 'itm_set_ring_test_20',
            'equipment_source_type' => 'set_equipment',
            'slot_type' => 'ring_1',
            'set_id' => 'set_test_20',
            'set_level' => 20,
            'star' => 1,
            'max_star' => 3,
            'quality' => 'purple',
            'rarity' => 'purple',
            'is_locked' => false,
            'is_equipped' => true,
            'obtained_at' => '2026-03-14 10:00:00',
        ]);

        $talisman = PlayerEquipmentInstance::query()->create([
            'player_id' => 10001,
            'instance_id' => 'eq_inst_test_talisman',
            'item_id' => 'itm_set_talisman_test_20',
            'equipment_source_type' => 'set_equipment',
            'slot_type' => 'talisman',
            'set_id' => 'set_test_20',
            'set_level' => 20,
            'star' => 3,
            'max_star' => 3,
            'quality' => 'purple',
            'rarity' => 'purple',
            'is_locked' => false,
            'is_equipped' => true,
            'obtained_at' => '2026-03-14 10:00:00',
        ]);

        PlayerEquipmentLoadout::query()->create([
            'player_id' => 10001,
            'slot_type' => 'main_weapon',
            'instance_id' => (string) $weapon->instance_id,
        ]);

        PlayerEquipmentLoadout::query()->create([
            'player_id' => 10001,
            'slot_type' => 'ring_1',
            'instance_id' => (string) $ring->instance_id,
        ]);

        PlayerEquipmentLoadout::query()->create([
            'player_id' => 10001,
            'slot_type' => 'talisman',
            'instance_id' => (string) $talisman->instance_id,
        ]);

        $setCounts = PlayerEquipmentLoadoutStatsService::equippedSetPieceCounts(10001);
        $starsBySlot = PlayerEquipmentLoadoutStatsService::trackedEquipmentStars(10001);

        $this->assertSame(['set_test_20' => 2], $setCounts);
        $this->assertSame(['main_weapon' => 3, 'ring_1' => 1], $starsBySlot);
        $this->assertArrayNotHasKey('talisman', $starsBySlot);
    }

    public function test_gem_slot_validation_rejects_unlocked_and_slot_group_mismatches(): void
    {
        $this->seed([ItemsSeeder::class]);

        $this->upsertItem('itm_set_weapon_test_20', '测试套装主武器', 'equipment', 'set_equipment', 'purple');

        PlayerEquipmentInstance::query()->create([
            'player_id' => 10001,
            'instance_id' => 'eq_inst_validation_weapon',
            'item_id' => 'itm_set_weapon_test_20',
            'equipment_source_type' => 'set_equipment',
            'slot_type' => 'main_weapon',
            'set_id' => 'set_test_20',
            'set_level' => 20,
            'star' => 3,
            'max_star' => 3,
            'quality' => 'purple',
            'rarity' => 'purple',
            'is_locked' => false,
            'is_equipped' => true,
            'obtained_at' => '2026-03-14 10:00:00',
        ]);

        $this->expectException(ValidationException::class);

        PlayerEquipmentGemSlot::query()->create([
            'instance_id' => 'eq_inst_validation_weapon',
            'slot_index' => 1,
            'slot_group' => 'attr_only',
            'required_star' => 3,
            'is_unlocked' => false,
            'gem_item_id' => 'itm_gem_attr_chijinshi_white',
        ]);
    }

    public function test_gem_slot_validation_rejects_wrong_gem_type_for_skill_slot(): void
    {
        $this->seed([ItemsSeeder::class]);

        $this->upsertItem('itm_set_weapon_test_20', '测试套装主武器', 'equipment', 'set_equipment', 'purple');

        PlayerEquipmentInstance::query()->create([
            'player_id' => 10001,
            'instance_id' => 'eq_inst_validation_weapon_2',
            'item_id' => 'itm_set_weapon_test_20',
            'equipment_source_type' => 'set_equipment',
            'slot_type' => 'main_weapon',
            'set_id' => 'set_test_60',
            'set_level' => 60,
            'star' => 8,
            'max_star' => 10,
            'quality' => 'purple',
            'rarity' => 'purple',
            'is_locked' => false,
            'is_equipped' => true,
            'obtained_at' => '2026-03-14 10:00:00',
        ]);

        $this->expectException(ValidationException::class);

        PlayerEquipmentGemSlot::query()->create([
            'instance_id' => 'eq_inst_validation_weapon_2',
            'slot_index' => 3,
            'slot_group' => 'skill_only',
            'required_star' => 8,
            'is_unlocked' => true,
            'gem_item_id' => 'itm_gem_attr_chijinshi_white',
        ]);
    }

    private function upsertItem(string $itemId, string $name, string $mainType, string $subType, string $quality): void
    {
        Item::query()->updateOrCreate(
            ['item_id' => $itemId],
            [
                'id' => $itemId,
                'item_id' => $itemId,
                'item_name' => $name,
                'display_name' => $name,
                'main_type' => $mainType,
                'sub_type' => $subType,
                'quality' => $quality,
                'rarity' => $quality,
                'icon' => null,
                'desc' => null,
                'is_stackable' => false,
                'max_stack' => 1,
                'is_enabled' => true,
                'sort_order' => 999000,
                'remark' => null,
                'source_library' => 'player_equipment_instance_examples',
                'required_level' => 1,
                'bind_type' => 'none',
                'sell_price' => 0,
                'use_type' => 'equip',
                'rarity_frame_key' => null,
                'name' => $name,
                'type' => 'item',
                'material_type' => null,
                'trait' => null,
                'effect_type' => null,
                'target_scope' => null,
                'effect_payload' => null,
                'drop_unlock_level' => 1,
                'socket_limit' => [],
                'source_tags' => ['player_equipment_instance_examples'],
                'use_tags' => ['equip'],
                'stack_limit' => 1,
                'can_compose' => false,
                'can_reforge' => false,
            ],
        );
    }
}
