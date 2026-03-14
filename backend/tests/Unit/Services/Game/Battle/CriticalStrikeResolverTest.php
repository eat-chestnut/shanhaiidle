<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\CriticalStrikeResolver;
use Tests\TestCase;

class CriticalStrikeResolverTest extends TestCase
{
    public function test_critical_rate_can_take_effect(): void
    {
        $example = $this->loadExamples()['critical_strike_example'];

        $result = app(CriticalStrikeResolver::class)->resolveWithForcedRoll(
            $example['attacker_unit'],
            [],
            (float) $example['forced_roll'],
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['is_critical']);
    }

    public function test_critical_damage_multiplier_can_take_effect(): void
    {
        $example = $this->loadExamples()['critical_strike_example'];

        $result = app(CriticalStrikeResolver::class)->resolveWithForcedRoll(
            $example['attacker_unit'],
            [],
            (float) $example['forced_roll'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_result']['critical_multiplier'], $result['data']['critical_multiplier']);
    }

    public function test_forced_roll_produces_predictable_result(): void
    {
        $attackerUnit = $this->loadExamples()['critical_strike_example']['attacker_unit'];
        $resolver = app(CriticalStrikeResolver::class);

        $criticalResult = $resolver->resolveWithForcedRoll($attackerUnit, [], 0.20);
        $nonCriticalResult = $resolver->resolveWithForcedRoll($attackerUnit, [], 0.30);

        $this->assertTrue($criticalResult['ok']);
        $this->assertTrue($nonCriticalResult['ok']);
        $this->assertTrue($criticalResult['data']['is_critical']);
        $this->assertFalse($nonCriticalResult['data']['is_critical']);
        $this->assertSame(1, $nonCriticalResult['data']['critical_multiplier']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/damage_formula_expansion_examples_v1.json')), true);
    }
}
