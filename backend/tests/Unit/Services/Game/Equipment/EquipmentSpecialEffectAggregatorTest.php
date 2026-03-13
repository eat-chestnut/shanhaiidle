<?php

namespace Tests\Unit\Services\Game\Equipment;

use App\Services\Game\Equipment\EquipmentSpecialEffectAggregator;
use PHPUnit\Framework\TestCase;

class EquipmentSpecialEffectAggregatorTest extends TestCase
{
    public function test_it_collects_special_effects_as_registration_only_records(): void
    {
        $aggregator = new EquipmentSpecialEffectAggregator();

        $result = $aggregator->aggregate(
            10001,
            [
                ['effect_key' => 'low_hp_shield', 'source' => 'talisman_jingang_tier_3', 'trigger_rule' => 'low_hp'],
            ],
            [
                ['effect_key' => 'boss_hunt_tag', 'source' => 'core_zhaoyao_burst'],
            ],
            [],
            []
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            ['effect_key' => 'low_hp_shield', 'source' => 'talisman_jingang_tier_3'],
            ['effect_key' => 'boss_hunt_tag', 'source' => 'core_zhaoyao_burst'],
        ], $result['data']);
        $this->assertSame('low_hp_shield', $result['data'][0]['effect_key']);
    }
}
