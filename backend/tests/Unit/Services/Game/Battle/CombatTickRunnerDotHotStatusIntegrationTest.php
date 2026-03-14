<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleRuntimeStateBuilder;
use App\Services\Game\Battle\CombatTickRunner;
use App\Services\Game\Battle\DotHotStateBuilder;
use App\Services\Game\Battle\StatusControlResolver;
use Tests\TestCase;

class CombatTickRunnerDotHotStatusIntegrationTest extends TestCase
{
    public function test_advanced_dot_hot_and_status_effects_are_processed_each_tick(): void
    {
        $examples = $this->loadExamples();
        $runtimeState = $this->buildCombinedRuntimeState($examples['combined_example']);

        $results = [];
        foreach ($examples['combined_example']['expected_runtime_results'] as $expectedTickResult) {
            $tick = (int) $expectedTickResult['tick'];
            $runtimeState['current_wave_index'] = 1;
            $runResult = app(CombatTickRunner::class)->runTick($runtimeState);

            $this->assertTrue($runResult['ok']);

            $runtimeState = $runResult['data'];
            $tickLogs = array_values(array_filter(
                $runtimeState['logs'],
                static fn (array $log): bool => (int) ($log['tick'] ?? 0) === $tick
            ));

            $results[] = [
                'tick' => $tick,
                'hp_damage' => array_sum(array_map(
                    static fn (array $log): int|float => ($log['action'] ?? '') === 'dot_hot_tick' ? ($log['hp_damage'] ?? 0) : 0,
                    $tickLogs
                )),
                'hp_healed' => array_sum(array_map(
                    static fn (array $log): int|float => ($log['action'] ?? '') === 'dot_hot_tick' ? ($log['hp_healed'] ?? 0) : 0,
                    $tickLogs
                )),
                'status' => $runtimeState['player_unit']['status'],
            ];
        }

        $this->assertSame($examples['combined_example']['expected_runtime_results'], $results);
        $this->assertSame('running', $runtimeState['status']);

        $dotHotLogs = array_values(array_filter(
            $runtimeState['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'dot_hot_tick'
        ));
        $statusLogs = array_values(array_filter(
            $runtimeState['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'status_effect'
        ));

        $this->assertCount(7, $dotHotLogs);
        $this->assertSame([
            [
                'tick' => 1,
                'actor' => 'enemy_mon_ice_witch_2_1',
                'target' => 'player_10001',
                'action' => 'status_effect',
                'effect_key' => 'slow_status',
                'status' => 'slowed',
                'status_applied' => true,
                'stack_count' => 1,
                'remaining_ticks' => 2,
            ],
            [
                'tick' => 2,
                'actor' => 'enemy_mon_ice_witch_2_1',
                'target' => 'player_10001',
                'action' => 'status_effect',
                'effect_key' => 'slow_status',
                'status' => 'slowed',
                'status_active' => true,
                'stack_count' => 1,
                'remaining_ticks' => 1,
            ],
            [
                'tick' => 3,
                'actor' => 'enemy_mon_ice_witch_2_1',
                'target' => 'player_10001',
                'action' => 'status_effect',
                'effect_key' => 'slow_status',
                'status' => 'slowed',
                'status_active' => false,
                'stack_count' => 0,
                'remaining_ticks' => 0,
            ],
        ], $statusLogs);
    }

    public function test_silenced_status_blocks_skill_cast_but_not_basic_attack(): void
    {
        $runtimeState = $this->buildRuntimeState([
            'battle_id' => 'battle_runtime_silence_test',
            'player_snapshot' => [
                'player_id' => 10001,
                'base_stats' => ['MELEE_ATK' => 120, 'HP' => 850, 'DEF' => 66],
                'bonus_stats' => [],
                'skills' => [[
                    'skill_id' => 'skill_slash',
                    'skill_type' => 'single_damage',
                    'damage_ratio' => 1.5,
                    'cooldown_ticks' => 3,
                    'auto_cast' => true,
                    'enabled' => true,
                ]],
                'special_effects' => [],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'mon_shadow_mage',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 500, 'ATK' => 0, 'DEF' => 12],
                    'skills' => [],
                    'tags' => [],
                    'special_effects' => [[
                        'effect_key' => 'silence_status',
                        'owner_unit_id' => 'enemy_mon_shadow_mage_1_1',
                        'target_unit_id' => 'player_10001',
                        'effect_type' => 'status_control',
                        'trigger_timing' => 'on_apply',
                        'duration_ticks' => 2,
                        'status' => 'silenced',
                    ]],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
        ]);

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame('silenced', $result['data']['player_unit']['status']);

        $skillCastLogs = array_values(array_filter(
            $result['data']['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'skill_cast'
        ));
        $basicAttackLogs = array_values(array_filter(
            $result['data']['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'basic_attack'
        ));

        $this->assertSame([], $skillCastLogs);
        $this->assertNotSame([], $basicAttackLogs);
        $this->assertSame('basic_attack', $basicAttackLogs[0]['action']);
    }

    /**
     * @param  array<string, mixed>  $combinedExample
     * @return array<string, mixed>
     */
    private function buildCombinedRuntimeState(array $combinedExample): array
    {
        $dotHotEffects = array_values(array_filter(
            $combinedExample['effects'],
            static fn (array $effect): bool => in_array($effect['effect_type'], ['dot', 'hot'], true)
        ));
        $statusEffects = array_values(array_filter(
            $combinedExample['effects'],
            static fn (array $effect): bool => $effect['effect_type'] === 'status_control'
        ));

        $dotHotBuildResult = app(DotHotStateBuilder::class)->build($dotHotEffects);
        $statusBuildResult = app(StatusControlResolver::class)->buildStates($statusEffects);

        $this->assertTrue($dotHotBuildResult['ok']);
        $this->assertTrue($statusBuildResult['ok']);

        return [
            'battle_id' => 'battle_runtime_dot_hot_status_advanced',
            'status' => 'running',
            'tick' => 0,
            'current_wave_index' => 1,
            'player_unit' => [
                'unit_id' => 'player_10001',
                'side' => 'player',
                'current_hp' => 200,
                'max_hp' => 300,
                'stats' => ['MELEE_ATK' => 0, 'HP' => 300, 'DEF' => 66],
                'bonus_stats' => [],
                'skills' => [],
                'runtime_effects' => [],
                'runtime_modifiers' => [],
                'runtime_tags' => [],
                'shield' => 0,
                'status' => null,
                'alive' => true,
            ],
            'enemy_units' => [
                [
                    'unit_id' => 'enemy_training_dummy_2_1',
                    'monster_id' => 'mon_training_dummy',
                    'side' => 'enemy',
                    'wave_index' => 2,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'current_hp' => 9999,
                    'max_hp' => 9999,
                    'stats' => ['HP' => 9999, 'ATK' => 0, 'DEF' => 9999],
                    'skills' => [],
                    'tags' => [],
                    'runtime_effects' => [],
                    'runtime_modifiers' => [],
                    'runtime_tags' => [],
                    'shield' => 0,
                    'status' => null,
                    'alive' => true,
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
            'dot_hot_states' => $dotHotBuildResult['data']['dot_hot_states'],
            'status_control_states' => $statusBuildResult['data']['status_control_states'],
            'logs' => [],
        ];
    }

    private function buildRuntimeState(array $payload): array
    {
        $buildResult = app(BattleRuntimeStateBuilder::class)->build($payload);

        return $buildResult['data'];
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v2.json')), true);
    }
}
