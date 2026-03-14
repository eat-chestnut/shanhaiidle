<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DamageModifierResolver;
use Tests\TestCase;

class DamageModifierResolverTest extends TestCase
{
    public function test_basic_attack_reads_bonus_melee_atk(): void
    {
        $example = $this->loadExamples()['basic_attack_modifier_example'];

        $result = app(DamageModifierResolver::class)->resolveBasicAttackModifiers(
            $example['attacker_unit'],
            $example['target_unit'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_result']['attack_multiplier'], $result['data']['attack_multiplier']);
    }

    public function test_skill_damage_reads_bonus_skill_dmg(): void
    {
        $example = $this->loadExamples()['skill_modifier_vs_boss_example'];

        $result = app(DamageModifierResolver::class)->resolveSkillDamageModifiers(
            $example['attacker_unit'],
            $example['target_unit'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_result']['skill_multiplier'], $result['data']['skill_multiplier']);
    }

    public function test_boss_target_reads_bonus_boss_dmg(): void
    {
        $example = $this->loadExamples()['skill_modifier_vs_boss_example'];

        $result = app(DamageModifierResolver::class)->resolveSkillDamageModifiers(
            $example['attacker_unit'],
            $example['target_unit'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_result']['boss_multiplier'], $result['data']['boss_multiplier']);
    }

    public function test_final_damage_bonus_enters_final_multiplier(): void
    {
        $example = $this->loadExamples()['basic_attack_modifier_example'];

        $result = app(DamageModifierResolver::class)->resolveBasicAttackModifiers(
            $example['attacker_unit'],
            $example['target_unit'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_result']['final_multiplier'], $result['data']['final_multiplier']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/damage_formula_expansion_examples_v1.json')), true);
    }
}
