<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\SkillDamageResolver;
use Tests\TestCase;

class SkillDamageResolverTest extends TestCase
{
    public function test_player_skill_damage_formula_is_applied(): void
    {
        $examples = $this->loadExamples();
        $example = $examples['player_skill_damage_example'];

        $result = app(SkillDamageResolver::class)->resolvePlayerSkillDamage(
            $example['player_unit'],
            $example['enemy_unit'],
            $example['skill_state'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_damage'], $result['data']['damage']);
    }

    public function test_bonus_skill_dmg_percent_is_effective(): void
    {
        $resolver = app(SkillDamageResolver::class);

        $withoutBonus = $resolver->resolvePlayerSkillDamage(
            [
                'stats' => ['MELEE_ATK' => 120],
                'bonus_stats' => [],
            ],
            [
                'stats' => ['DEF' => 12],
                'is_boss' => false,
            ],
            [
                'skill_id' => 'skill_slash',
                'skill_type' => 'single_damage',
                'damage_ratio' => 1.5,
            ],
        );

        $withBonus = $resolver->resolvePlayerSkillDamage(
            [
                'stats' => ['MELEE_ATK' => 120],
                'bonus_stats' => [
                    'bonus_skill_dmg' => [
                        [
                            'value_type' => 'percent',
                            'value' => 10,
                            'source' => 'talisman_star_link_6',
                        ],
                    ],
                ],
            ],
            [
                'stats' => ['DEF' => 12],
                'is_boss' => false,
            ],
            [
                'skill_id' => 'skill_slash',
                'skill_type' => 'single_damage',
                'damage_ratio' => 1.5,
            ],
        );

        $this->assertTrue($withoutBonus['ok']);
        $this->assertTrue($withBonus['ok']);
        $this->assertTrue($withBonus['data']['damage'] > $withoutBonus['data']['damage']);
    }

    public function test_enemy_skill_damage_is_at_least_one(): void
    {
        $result = app(SkillDamageResolver::class)->resolveEnemySkillDamage(
            [
                'stats' => ['ATK' => 10],
            ],
            [
                'stats' => ['DEF' => 999],
            ],
            [
                'skill_id' => 'skill_frost_breath',
                'skill_type' => 'single_damage',
                'damage_ratio' => 1.0,
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['data']['damage']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/skill_execution_minimal_examples_v1.json')), true);
    }
}
