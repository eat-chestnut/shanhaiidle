<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleRuntimeStateBuilder;
use App\Services\Game\Battle\CombatTickRunner;
use Tests\TestCase;

class CombatTickRunnerTest extends TestCase
{
    public function test_single_tick_advances_runtime_state(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'player_snapshot' => [
                'player_id' => 1,
                'base_stats' => ['MELEE_ATK' => 30, 'HP' => 100, 'DEF' => 5],
                'bonus_stats' => [],
                'special_effects' => [],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'enemy_a',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 50, 'ATK' => 10, 'DEF' => 3],
                    'skills' => [],
                    'tags' => [],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
            'debug_sources' => [],
        ]);

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['data']['tick']);
        $this->assertSame(95, $result['data']['player_unit']['current_hp']);
        $this->assertSame(23, $result['data']['enemy_units'][0]['current_hp']);
        $this->assertSame('basic_attack', $result['data']['logs'][0]['action']);
    }

    public function test_player_attacks_current_wave_target_with_lowest_unit_index(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'player_snapshot' => [
                'player_id' => 1,
                'base_stats' => ['MELEE_ATK' => 20, 'HP' => 100, 'DEF' => 0],
                'bonus_stats' => [],
                'special_effects' => [],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'enemy_b',
                    'wave_index' => 1,
                    'unit_index' => 2,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 50, 'ATK' => 0, 'DEF' => 0],
                    'skills' => [],
                    'tags' => [],
                ],
                [
                    'monster_id' => 'enemy_a',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 50, 'ATK' => 0, 'DEF' => 0],
                    'skills' => [],
                    'tags' => [],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
            'debug_sources' => [],
        ]);

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertSame('enemy_enemy_a_1_1', $result['data']['logs'][0]['target']);
        $this->assertSame(30, $result['data']['enemy_units'][0]['current_hp']);
        $this->assertSame(50, $result['data']['enemy_units'][1]['current_hp']);
    }

    public function test_alive_enemies_in_current_wave_can_counterattack_player(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'player_snapshot' => [
                'player_id' => 1,
                'base_stats' => ['MELEE_ATK' => 5, 'HP' => 100, 'DEF' => 3],
                'bonus_stats' => [],
                'special_effects' => [],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'enemy_a',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 20, 'ATK' => 8, 'DEF' => 0],
                    'skills' => [],
                    'tags' => [],
                ],
                [
                    'monster_id' => 'enemy_b',
                    'wave_index' => 1,
                    'unit_index' => 2,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 20, 'ATK' => 9, 'DEF' => 0],
                    'skills' => [],
                    'tags' => [],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
            'debug_sources' => [],
        ]);

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertSame(89, $result['data']['player_unit']['current_hp']);
        $this->assertSame('enemy_enemy_a_1_1', $result['data']['logs'][1]['actor']);
        $this->assertSame('enemy_enemy_b_1_2', $result['data']['logs'][2]['actor']);
    }

    public function test_it_switches_to_next_wave_after_current_wave_is_cleared(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'player_snapshot' => [
                'player_id' => 1,
                'base_stats' => ['MELEE_ATK' => 60, 'HP' => 100, 'DEF' => 0],
                'bonus_stats' => [],
                'special_effects' => [],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'enemy_a',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 30, 'ATK' => 0, 'DEF' => 0],
                    'skills' => [],
                    'tags' => [],
                ],
                [
                    'monster_id' => 'enemy_b',
                    'wave_index' => 2,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 40, 'ATK' => 0, 'DEF' => 0],
                    'skills' => [],
                    'tags' => [],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
            'debug_sources' => [],
        ]);

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertSame(2, $result['data']['current_wave_index']);
        $this->assertFalse($result['data']['enemy_units'][0]['alive']);
        $this->assertSame('wave_switch', $result['data']['logs'][2]['action']);
        $this->assertSame(2, $result['data']['logs'][2]['wave_index']);
    }

    private function buildRuntimeState(array $payload): array
    {
        $buildResult = app(BattleRuntimeStateBuilder::class)->build($payload);

        return $buildResult['data'];
    }
}
