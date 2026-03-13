<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\TalismanStarLinkResolver;
use PHPUnit\Framework\TestCase;

class TalismanStarLinkResolverTest extends TestCase
{
    public function test_resolve_qualifies_six_star_link_and_rejects_eight_star_link_with_reason(): void
    {
        $resolver = new TalismanStarLinkResolver();

        $trackedSlots = [
            'main_weapon',
            'sub_weapon',
            'armor',
            'leg',
            'shoe',
            'cloak',
            'helmet',
            'necklace',
            'bracelet_1',
            'bracelet_2',
            'ring_1',
            'ring_2',
        ];

        $loadouts = [];
        $equipmentInstances = [];
        foreach ($trackedSlots as $slotType) {
            $instanceId = 'eq_inst_'.$slotType;
            $loadouts[] = [
                'slot_type' => $slotType,
                'instance_id' => $instanceId,
            ];
            $equipmentInstances[] = [
                'instance_id' => $instanceId,
                'slot_type' => $slotType,
                'star' => 6,
            ];
        }

        $loadouts[] = ['slot_type' => 'talisman', 'instance_id' => 'eq_inst_talisman'];
        $equipmentInstances[] = [
            'instance_id' => 'eq_inst_talisman',
            'slot_type' => 'talisman',
            'star' => 0,
        ];

        $talismanInstance = [
            'instance_id' => 'eq_inst_talisman',
            'slot_type' => 'talisman',
            'talisman_id' => 'tal_test_40',
        ];

        $talismanStarLinks = [
            [
                'talisman_id' => 'tal_test_40',
                'required_equipment_star' => 6,
                'effect_key' => 'bonus_skill_dmg',
                'value_type' => 'percent',
                'value' => 5,
            ],
            [
                'talisman_id' => 'tal_test_40',
                'required_equipment_star' => 8,
                'effect_key' => 'bonus_crit_dmg',
                'value_type' => 'percent',
                'value' => 10,
            ],
            [
                'talisman_id' => 'tal_other',
                'required_equipment_star' => 6,
                'effect_key' => 'bonus_hp',
                'value_type' => 'flat',
                'value' => 100,
            ],
        ];

        $result = $resolver->resolve($loadouts, $equipmentInstances, $talismanInstance, $talismanStarLinks);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);

        $this->assertSame([6], array_column($result['data']['qualified_star_links'], 'required_equipment_star'));
        $this->assertSame([8], array_column($result['data']['missing_requirements'], 'required_equipment_star'));
        $this->assertSame(
            'one or more participating slots have stars below 8',
            $result['data']['missing_requirements'][0]['reason']
        );
    }
}
