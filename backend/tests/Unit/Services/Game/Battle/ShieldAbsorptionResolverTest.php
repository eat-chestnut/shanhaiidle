<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\ShieldAbsorptionResolver;
use Tests\TestCase;

class ShieldAbsorptionResolverTest extends TestCase
{
    public function test_shield_is_consumed_before_hp(): void
    {
        $result = app(ShieldAbsorptionResolver::class)->resolve(
            [
                'unit_id' => 'enemy_guard',
                'shield' => 50,
                'current_hp' => 300,
            ],
            30,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(30, $result['data']['absorbed_by_shield']);
        $this->assertSame(0, $result['data']['hp_damage']);
        $this->assertSame(20, $result['data']['remaining_shield']);
    }

    public function test_remaining_damage_enters_hp_when_shield_is_insufficient(): void
    {
        $example = $this->loadExamples()['shield_absorption_example'];

        $result = app(ShieldAbsorptionResolver::class)->resolve(
            $example['target_unit'],
            $example['incoming_damage'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_result']['absorbed_by_shield'], $result['data']['absorbed_by_shield']);
        $this->assertSame($example['expected_result']['hp_damage'], $result['data']['hp_damage']);
        $this->assertSame($example['expected_result']['remaining_shield'], $result['data']['remaining_shield']);
    }

    public function test_all_damage_enters_hp_when_no_shield_exists(): void
    {
        $result = app(ShieldAbsorptionResolver::class)->resolve(
            [
                'unit_id' => 'enemy_guard',
                'shield' => 0,
                'current_hp' => 300,
            ],
            80,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['data']['absorbed_by_shield']);
        $this->assertSame(80, $result['data']['hp_damage']);
        $this->assertSame(0, $result['data']['remaining_shield']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/damage_formula_expansion_examples_v1.json')), true);
    }
}
