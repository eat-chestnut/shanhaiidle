<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BasicDamageResolver;
use Tests\TestCase;

class BasicDamageResolverTest extends TestCase
{
    public function test_player_attack_deals_at_least_one_damage(): void
    {
        $result = app(BasicDamageResolver::class)->resolvePlayerToEnemy(
            [
                'stats' => ['MELEE_ATK' => 5],
                'bonus_stats' => [],
            ],
            [
                'stats' => ['DEF' => 99],
                'is_boss' => false,
            ]
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['data']['damage']);
    }

    public function test_enemy_attack_deals_at_least_one_damage(): void
    {
        $result = app(BasicDamageResolver::class)->resolveEnemyToPlayer(
            [
                'stats' => ['ATK' => 5],
            ],
            [
                'stats' => ['DEF' => 99],
            ]
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['data']['damage']);
    }

    public function test_bonus_melee_atk_changes_player_damage(): void
    {
        $resolver = app(BasicDamageResolver::class);

        $baseResult = $resolver->resolvePlayerToEnemy(
            [
                'stats' => ['MELEE_ATK' => 100],
                'bonus_stats' => [],
            ],
            [
                'stats' => ['DEF' => 20],
                'is_boss' => false,
            ]
        );
        $bonusResult = $resolver->resolvePlayerToEnemy(
            [
                'stats' => ['MELEE_ATK' => 100],
                'bonus_stats' => [
                    'bonus_melee_atk' => [
                        ['value_type' => 'percent', 'value' => 10],
                    ],
                ],
            ],
            [
                'stats' => ['DEF' => 20],
                'is_boss' => false,
            ]
        );

        $this->assertSame(80, $baseResult['data']['damage']);
        $this->assertSame(90, $bonusResult['data']['damage']);
    }

    public function test_bonus_boss_dmg_only_applies_to_boss_targets(): void
    {
        $resolver = app(BasicDamageResolver::class);
        $playerUnit = [
            'stats' => ['MELEE_ATK' => 100],
            'bonus_stats' => [
                'bonus_boss_dmg' => [
                    ['value_type' => 'percent', 'value' => 50],
                ],
            ],
        ];

        $normalEnemyResult = $resolver->resolvePlayerToEnemy(
            $playerUnit,
            [
                'stats' => ['DEF' => 20],
                'is_boss' => false,
            ]
        );
        $bossEnemyResult = $resolver->resolvePlayerToEnemy(
            $playerUnit,
            [
                'stats' => ['DEF' => 20],
                'is_boss' => true,
            ]
        );

        $this->assertSame(80, $normalEnemyResult['data']['damage']);
        $this->assertSame(130, $bossEnemyResult['data']['damage']);
    }
}
