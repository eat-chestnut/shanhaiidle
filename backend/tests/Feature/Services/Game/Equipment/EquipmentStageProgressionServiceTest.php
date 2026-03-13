<?php

namespace Tests\Feature\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentStageProgressionService;

class EquipmentStageProgressionServiceTest extends EquipmentTransactionServiceTestCase
{
    public function test_can_progress_when_current_star_is_maxed(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_20_progress_a',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );
        $this->createGemSlots((string) $instance->instance_id, [1], [1 => 'itm_gem_attr_chijinshi_white']);

        $recipe = $this->progressionRecipeFor('itm_set_zhaoyao_20_main_weapon', 'main_weapon');
        $requiredItems = $this->progressionRequiredItems($recipe);
        $profileState = $this->profileStateForCosts($requiredItems);
        $this->createProfile($playerId, $profileState['inventory'], $profileState['gold'], $profileState['crystal'], $profileState['contribution']);

        $result = app(EquipmentStageProgressionService::class)->execute($playerId, (string) $instance->instance_id);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'instance_id' => 'eq_inst_set_weapon_20_progress_a',
            'old_item_id' => 'itm_set_zhaoyao_20_main_weapon',
            'new_item_id' => (string) $recipe->result_item_id,
            'old_set_level' => 20,
            'new_set_level' => (int) $recipe->equipmentSet->set_level,
            'star' => 3,
            'max_star' => 6,
        ], $result['data']);

        $instance->refresh();
        $this->assertSame((string) $recipe->result_item_id, (string) $instance->item_id);
        $this->assertSame((int) $recipe->equipmentSet->set_level, (int) $instance->set_level);
        $this->assertSame(3, (int) $instance->star);
        $this->assertSame(6, (int) $instance->max_star);
        $this->assertDatabaseHas('player_equipment_gem_slots', [
            'instance_id' => 'eq_inst_set_weapon_20_progress_a',
            'slot_index' => 1,
            'gem_item_id' => 'itm_gem_attr_chijinshi_white',
        ]);
    }

    public function test_cannot_progress_when_current_star_is_not_maxed(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10001;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_20_progress_b',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            2,
            3,
        );
        $this->createProfile($playerId);

        $result = app(EquipmentStageProgressionService::class)->execute($playerId, (string) $instance->instance_id);

        $this->assertFalse($result['ok']);
        $this->assertSame('current star is 2, required max star is 3', $result['reason']);

        $instance->refresh();
        $this->assertSame('itm_set_zhaoyao_20_main_weapon', (string) $instance->item_id);
        $this->assertSame(20, (int) $instance->set_level);
    }

    public function test_fails_when_blueprint_or_material_is_missing(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $recipe = $this->progressionRecipeFor('itm_set_zhaoyao_20_main_weapon', 'main_weapon');
        $requiredItems = $this->progressionRequiredItems($recipe);
        $firstRequired = $requiredItems[0];

        foreach ([0, 1] as $index) {
            $playerId = 12000 + $index;
            $instanceId = 'eq_inst_set_weapon_20_progress_fail_' . $index;
            $instance = $this->createInstance(
                $playerId,
                $instanceId,
                'itm_set_zhaoyao_20_main_weapon',
                'set_equipment',
                'main_weapon',
                'set_zhaoyao_20',
                20,
                3,
                3,
            );

            $profileState = $this->profileStateForCosts($requiredItems);
            if ($index === 0) {
                $profileState['inventory'][(string) $firstRequired['item_id']] = 0;
                unset($profileState['inventory'][(string) $firstRequired['item_id']]);
            } else {
                $lastRequired = $requiredItems[count($requiredItems) - 1];
                if ((string) $lastRequired['item_id'] === 'cur_gold') {
                    $profileState['gold'] = max(0, $profileState['gold'] - 1);
                } else {
                    $profileState['inventory'][(string) $lastRequired['item_id']] = max(0, (int) ($profileState['inventory'][(string) $lastRequired['item_id']] ?? 0) - 1);
                }
            }

            $this->createProfile($playerId, $profileState['inventory'], $profileState['gold'], $profileState['crystal'], $profileState['contribution']);

            $result = app(EquipmentStageProgressionService::class)->execute($playerId, $instanceId);

            $this->assertFalse($result['ok']);
            $this->assertStringContainsString('insufficient', (string) $result['reason']);

            $instance->refresh();
            $this->assertSame('itm_set_zhaoyao_20_main_weapon', (string) $instance->item_id);
            $this->assertSame(20, (int) $instance->set_level);
        }
    }

    public function test_progression_updates_item_set_level_and_max_star_and_keeps_star(): void
    {
        $this->seedEquipmentTransactionDependencies();

        $playerId = 10002;
        $instance = $this->createInstance(
            $playerId,
            'eq_inst_set_weapon_20_progress_c',
            'itm_set_zhaoyao_20_main_weapon',
            'set_equipment',
            'main_weapon',
            'set_zhaoyao_20',
            20,
            3,
            3,
        );

        $recipe = $this->progressionRecipeFor('itm_set_zhaoyao_20_main_weapon', 'main_weapon');
        $requiredItems = $this->progressionRequiredItems($recipe);
        $profileState = $this->profileStateForCosts($requiredItems);
        $this->createProfile($playerId, $profileState['inventory'], $profileState['gold'], $profileState['crystal'], $profileState['contribution']);

        $result = app(EquipmentStageProgressionService::class)->execute($playerId, (string) $instance->instance_id);

        $this->assertTrue($result['ok']);

        $instance->refresh();
        $this->assertSame((string) $recipe->result_item_id, (string) $instance->item_id);
        $this->assertSame((int) $recipe->equipmentSet->set_level, (int) $instance->set_level);
        $this->assertSame(6, (int) $instance->max_star);
        $this->assertSame(3, (int) $instance->star);
    }
}
