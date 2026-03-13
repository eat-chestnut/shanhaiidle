<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentGemSlotResolver;
use PHPUnit\Framework\TestCase;

class EquipmentGemSlotResolverTest extends TestCase
{
    private const SLOT_UNLOCK_RULES = [
        ['required_star' => 3, 'slot_index' => 1, 'slot_group' => 'attr_only'],
        ['required_star' => 6, 'slot_index' => 2, 'slot_group' => 'attr_only'],
        ['required_star' => 8, 'slot_index' => 3, 'slot_group' => 'skill_only'],
        ['required_star' => 10, 'slot_index' => 4, 'slot_group' => 'skill_only'],
    ];

    public function test_three_star_equipment_unlocks_only_first_slot(): void
    {
        $resolver = new EquipmentGemSlotResolver();

        $result = $resolver->resolveUnlockedSlots([
            'instance_id' => 'eq_inst_3_star',
            'star' => 3,
        ], self::SLOT_UNLOCK_RULES);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([1], array_column($result['data']['unlocked_slots'], 'slot_index'));
    }

    public function test_six_star_equipment_unlocks_first_two_slots(): void
    {
        $resolver = new EquipmentGemSlotResolver();

        $result = $resolver->resolveUnlockedSlots([
            'instance_id' => 'eq_inst_6_star',
            'star' => 6,
        ], self::SLOT_UNLOCK_RULES);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([1, 2], array_column($result['data']['unlocked_slots'], 'slot_index'));
    }

    public function test_attr_only_slot_rejects_skill_only_gem(): void
    {
        $resolver = new EquipmentGemSlotResolver();

        $result = $resolver->canSocketGem(
            ['instance_id' => 'eq_inst_10_star', 'star' => 10],
            1,
            ['item_id' => 'itm_gem_skill_test'],
            ['item_id' => 'itm_gem_skill_test', 'slot_group' => 'skill_only'],
            self::SLOT_UNLOCK_RULES
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('gem_slot_group_mismatch', $result['reason']);
        $this->assertFalse($result['data']['allowed']);
    }

    public function test_locked_slot_rejects_socketing(): void
    {
        $resolver = new EquipmentGemSlotResolver();

        $result = $resolver->canSocketGem(
            ['instance_id' => 'eq_inst_3_star', 'star' => 3],
            2,
            ['item_id' => 'itm_gem_attr_test'],
            ['item_id' => 'itm_gem_attr_test', 'slot_group' => 'attr_only'],
            self::SLOT_UNLOCK_RULES
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('slot_not_unlocked', $result['reason']);
        $this->assertFalse($result['data']['allowed']);
    }
}
