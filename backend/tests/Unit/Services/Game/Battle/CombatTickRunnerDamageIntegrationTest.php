<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleRuntimeStateBuilder;
use App\Services\Game\Battle\CombatTickRunner;
use Tests\TestCase;

class CombatTickRunnerDamageIntegrationTest extends TestCase
{
    public function test_basic_attack_log_contains_expanded_damage_fields(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'player_snapshot' => [
                'player_id' => 10001,
                'base_stats' => ['MELEE_ATK' => 120, 'HP' => 850, 'DEF' => 66],
                'bonus_stats' => [],
                'skills' => [],
                'special_effects' => [],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'mon_qingqiu_guard',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 500, 'ATK' => 40, 'DEF' => 12],
                    'skills' => [],
                    'tags' => [],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
        ]);
        $runtimeState['enemy_units'][0]['shield'] = 10;

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'tick' => 1,
            'actor' => 'player_10001',
            'target' => 'enemy_mon_qingqiu_guard_1_1',
            'action' => 'basic_attack',
            'skill_id' => null,
            'raw_damage' => 108,
            'is_critical' => false,
            'shield_absorbed' => 10,
            'hp_damage' => 98,
        ], $result['data']['logs'][0]);
    }

    public function test_skill_log_contains_expanded_damage_fields(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'player_snapshot' => [
                'player_id' => 10001,
                'base_stats' => ['MELEE_ATK' => 120, 'HP' => 850, 'DEF' => 66],
                'bonus_stats' => [
                    'bonus_skill_dmg' => [
                        [
                            'value_type' => 'percent',
                            'value' => 10,
                            'source' => 'talisman_star_link_6',
                        ],
                    ],
                ],
                'skills' => [
                    [
                        'skill_id' => 'skill_slash',
                        'skill_type' => 'single_damage',
                        'damage_ratio' => 1.5,
                        'cooldown_ticks' => 3,
                        'auto_cast' => true,
                        'enabled' => true,
                    ],
                ],
                'special_effects' => [],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'mon_qingqiu_guard',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 500, 'ATK' => 40, 'DEF' => 12],
                    'skills' => [],
                    'tags' => [],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
        ]);
        $runtimeState['enemy_units'][0]['shield'] = 20;

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'tick' => 1,
            'actor' => 'player_10001',
            'target' => 'enemy_mon_qingqiu_guard_1_1',
            'action' => 'skill_cast',
            'skill_id' => 'skill_slash',
            'raw_damage' => 186,
            'is_critical' => false,
            'shield_absorbed' => 20,
            'hp_damage' => 166,
        ], $result['data']['logs'][0]);
    }

    private function buildRuntimeState(array $payload): array
    {
        $buildResult = app(BattleRuntimeStateBuilder::class)->build($payload);

        return $buildResult['data'];
    }
}
