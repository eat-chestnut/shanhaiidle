<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleRuntimeStateBuilder;
use App\Services\Game\Battle\CombatTickRunner;
use Tests\TestCase;

class CombatTickRunnerSkillIntegrationTest extends TestCase
{
    public function test_player_prefers_skill_cast_when_castable(): void
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

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame('skill_cast', $result['data']['logs'][0]['action']);
        $this->assertSame('skill_slash', $result['data']['logs'][0]['skill_id']);
        $this->assertSame(186, $result['data']['logs'][0]['damage']);
        $this->assertSame(314, $result['data']['enemy_units'][0]['current_hp']);
        $this->assertSame(2, $result['data']['player_unit']['skill_states'][0]['cooldown_remaining']);
    }

    public function test_falls_back_to_basic_attack_when_no_skill_can_cast(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'player_snapshot' => [
                'player_id' => 10001,
                'base_stats' => ['MELEE_ATK' => 120, 'HP' => 850, 'DEF' => 66],
                'bonus_stats' => [],
                'skills' => [
                    [
                        'skill_id' => 'skill_slash',
                        'skill_type' => 'single_damage',
                        'damage_ratio' => 1.5,
                        'cooldown_ticks' => 3,
                        'cooldown_remaining' => 2,
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

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame('basic_attack', $result['data']['logs'][0]['action']);
        $this->assertArrayNotHasKey('skill_id', $result['data']['logs'][0]);
        $this->assertSame(1, $result['data']['player_unit']['skill_states'][0]['cooldown_remaining']);
    }

    public function test_skill_cast_log_is_written_with_required_fields(): void
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

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $skillLogs = array_values(array_filter(
            $result['data']['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'skill_cast'
        ));

        $this->assertNotSame([], $skillLogs);
        $this->assertSame([
            'tick' => 1,
            'actor' => 'player_10001',
            'target' => 'enemy_mon_qingqiu_guard_1_1',
            'action' => 'skill_cast',
            'skill_id' => 'skill_slash',
            'damage' => 186,
        ], $skillLogs[0]);
    }

    private function buildRuntimeState(array $payload): array
    {
        $buildResult = app(BattleRuntimeStateBuilder::class)->build($payload);

        return $buildResult['data'];
    }
}
