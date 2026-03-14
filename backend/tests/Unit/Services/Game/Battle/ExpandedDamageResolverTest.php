<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\ExpandedDamageResolver;
use Tests\TestCase;

class ExpandedDamageResolverTest extends TestCase
{
    public function test_basic_attack_can_be_resolved_correctly(): void
    {
        $result = app(ExpandedDamageResolver::class)->resolveBasicAttack(
            [
                'stats' => ['MELEE_ATK' => 100],
                'bonus_stats' => [
                    'bonus_melee_atk' => [
                        ['value_type' => 'percent', 'value' => 10, 'source' => 'set_zhaoyao_40_2pc'],
                    ],
                ],
            ],
            [
                'stats' => ['DEF' => 20],
                'shield' => 0,
                'current_hp' => 500,
                'is_boss' => false,
            ],
            ['forced_roll' => 0.99],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(90, $result['data']['raw_damage']);
        $this->assertFalse($result['data']['is_critical']);
        $this->assertSame(0, $result['data']['shield_absorbed']);
        $this->assertSame(90, $result['data']['hp_damage']);
    }

    public function test_skill_damage_can_be_resolved_correctly(): void
    {
        $example = $this->loadExamples()['expanded_skill_damage_example'];

        $result = app(ExpandedDamageResolver::class)->resolveSkillDamage(
            $example['attacker_unit'],
            $example['target_unit'],
            $example['skill_state'],
            ['forced_roll' => (float) $example['forced_roll']],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(328.524, $result['data']['raw_damage']);
        $this->assertTrue($result['data']['is_critical']);
        $this->assertSame(50, $result['data']['shield_absorbed']);
        $this->assertSame(278.524, $result['data']['hp_damage']);
    }

    public function test_critical_damage_can_take_effect(): void
    {
        $result = app(ExpandedDamageResolver::class)->resolveBasicAttack(
            [
                'stats' => ['MELEE_ATK' => 100],
                'bonus_stats' => [
                    'bonus_crit_rate' => [
                        ['value_type' => 'percent', 'value' => 100, 'source' => 'crit_test'],
                    ],
                    'bonus_crit_dmg' => [
                        ['value_type' => 'percent', 'value' => 50, 'source' => 'crit_test'],
                    ],
                ],
            ],
            [
                'stats' => ['DEF' => 0],
                'shield' => 0,
                'current_hp' => 500,
                'is_boss' => false,
            ],
            ['forced_roll' => 0.20],
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['is_critical']);
        $this->assertSame(150, $result['data']['raw_damage']);
    }

    public function test_boss_damage_bonus_can_take_effect(): void
    {
        $resolver = app(ExpandedDamageResolver::class);

        $normalTargetResult = $resolver->resolveBasicAttack(
            [
                'stats' => ['MELEE_ATK' => 100],
                'bonus_stats' => [
                    'bonus_boss_dmg' => [
                        ['value_type' => 'percent', 'value' => 50, 'source' => 'boss_test'],
                    ],
                ],
            ],
            [
                'stats' => ['DEF' => 20],
                'shield' => 0,
                'current_hp' => 500,
                'is_boss' => false,
            ],
            ['forced_roll' => 0.99],
        );
        $bossTargetResult = $resolver->resolveBasicAttack(
            [
                'stats' => ['MELEE_ATK' => 100],
                'bonus_stats' => [
                    'bonus_boss_dmg' => [
                        ['value_type' => 'percent', 'value' => 50, 'source' => 'boss_test'],
                    ],
                ],
            ],
            [
                'stats' => ['DEF' => 20],
                'shield' => 0,
                'current_hp' => 500,
                'is_boss' => true,
            ],
            ['forced_roll' => 0.99],
        );

        $this->assertTrue($normalTargetResult['ok']);
        $this->assertTrue($bossTargetResult['ok']);
        $this->assertSame(80, $normalTargetResult['data']['raw_damage']);
        $this->assertSame(130, $bossTargetResult['data']['raw_damage']);
    }

    public function test_shield_absorption_can_take_effect(): void
    {
        $result = app(ExpandedDamageResolver::class)->resolveBasicAttack(
            [
                'stats' => ['MELEE_ATK' => 100],
                'bonus_stats' => [],
            ],
            [
                'stats' => ['DEF' => 20],
                'shield' => 30,
                'current_hp' => 500,
                'is_boss' => false,
            ],
            ['forced_roll' => 0.99],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(80, $result['data']['raw_damage']);
        $this->assertSame(30, $result['data']['shield_absorbed']);
        $this->assertSame(50, $result['data']['hp_damage']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/damage_formula_expansion_examples_v1.json')), true);
    }
}
