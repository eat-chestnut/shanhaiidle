<?php

namespace Tests\Feature\Services\Game\Equipment;

use App\Models\PlayerEquipmentGemSlot;
use App\Services\Game\Equipment\EquipmentStarUpgradeService;

class EquipmentStarUpgradeServiceTest extends EquipmentTransactionServiceTestCase
{
    public function test_can_upgrade_star_when_materials_are_sufficient(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_20_a001',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            2,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id);

        $costs = $this->starUpgradeCostsFor(20, 2, 3);
        $profileState = $this->profileStateForCosts($costs);
        $this->createProfile($playerId, $profileState['inventory'], $profileState['gold'], $profileState['crystal'], $profileState['contribution']);

        $result = app(EquipmentStarUpgradeService::class)->execute($playerId, (string) $instance->instance_id);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'instance_id' => 'eq_inst_set_weapon_20_a001',
            'old_star' => 2,
            'new_star' => 3,
            'newly_unlocked_slots' => [1],
        ], $result['data']);

        $instance->refresh();
        $this->assertSame(3, (int) $instance->star);
        $this->assertTrue((bool) PlayerEquipmentGemSlot::query()
            ->where('instance_id', 'eq_inst_set_weapon_20_a001')
            ->where('slot_index', 1)
            ->value('is_unlocked'));
    }

    public function test_fails_when_materials_are_insufficient(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_20_b001',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            2,
            3,
        );

        $this->createProfile($playerId, [], 0, 0, 0);

        $result = app(EquipmentStarUpgradeService::class)->execute($playerId, (string) $instance->instance_id);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('insufficient', (string) $result['reason']);

        $instance->refresh();
        $this->assertSame(2, (int) $instance->star);
    }

    public function test_successful_upgrade_increments_star_by_one(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_40_a001',
            'itm_set_zhaoyao_40_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_40',
            40,
            0,
            6,
        );

        $costs = $this->starUpgradeCostsFor(40, 0, 1);
        $profileState = $this->profileStateForCosts($costs);
        $this->createProfile($playerId, $profileState['inventory'], $profileState['gold'], $profileState['crystal'], $profileState['contribution']);

        $result = app(EquipmentStarUpgradeService::class)->execute($playerId, (string) $instance->instance_id);

        $this->assertTrue($result['ok']);
        $instance->refresh();
        $this->assertSame(1, (int) $instance->star);
    }

    public function test_unlocks_expected_slots_at_3_6_8_and_10_star(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $cases = [
            ['instance_id' => 'eq_inst_star_3', 'item_id' => 'itm_set_zhaoyao_20_main_weapon', 'set_id' => 'set_zhaoyao_20', 'set_level' => 20, 'from_star' => 2, 'to_star' => 3, 'max_star' => 3, 'expected_slot' => 1],
            ['instance_id' => 'eq_inst_star_6', 'item_id' => 'itm_set_zhaoyao_40_main_weapon', 'set_id' => 'set_zhaoyao_40', 'set_level' => 40, 'from_star' => 5, 'to_star' => 6, 'max_star' => 6, 'expected_slot' => 2],
            ['instance_id' => 'eq_inst_star_8', 'item_id' => 'itm_set_zhaoyao_50_main_weapon', 'set_id' => 'set_zhaoyao_50', 'set_level' => 50, 'from_star' => 7, 'to_star' => 8, 'max_star' => 8, 'expected_slot' => 3],
            ['instance_id' => 'eq_inst_star_10', 'item_id' => 'itm_set_zhaoyao_60_main_weapon', 'set_id' => 'set_zhaoyao_60', 'set_level' => 60, 'from_star' => 9, 'to_star' => 10, 'max_star' => 10, 'expected_slot' => 4],
        ];

        foreach ($cases as $index => $case) {
            $playerId = 11000 + $index;
            $instance = $this->createInstance(
                $playerId,
                $case['instance_id'],
                $case['item_id'],
                'set_equipment',
                'main_weapon',
                $case['set_id'],
                $case['set_level'],
                $case['from_star'],
                $case['max_star'],
            );
            $alreadyUnlocked = match ($case['expected_slot']) {
                1 => [],
                2 => [1],
                3 => [1, 2],
                4 => [1, 2, 3],
            };
            $this->createGemSlots((string) $instance->instance_id, $alreadyUnlocked);

            $costs = $this->starUpgradeCostsFor($case['set_level'], $case['from_star'], $case['to_star']);
            $profileState = $this->profileStateForCosts($costs);
            $this->createProfile($playerId, $profileState['inventory'], $profileState['gold'], $profileState['crystal'], $profileState['contribution']);

            $result = app(EquipmentStarUpgradeService::class)->execute($playerId, $case['instance_id']);

            $this->assertTrue($result['ok']);
            $this->assertSame([$case['expected_slot']], $result['data']['newly_unlocked_slots']);
            $this->assertTrue((bool) PlayerEquipmentGemSlot::query()
                ->where('instance_id', $case['instance_id'])
                ->where('slot_index', $case['expected_slot'])
                ->value('is_unlocked'));
        }
    }
}
