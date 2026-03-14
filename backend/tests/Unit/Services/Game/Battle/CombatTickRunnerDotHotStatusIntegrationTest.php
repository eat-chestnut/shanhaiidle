<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleRuntimeStateBuilder;
use App\Services\Game\Battle\CombatTickRunner;
use Tests\TestCase;

class CombatTickRunnerDotHotStatusIntegrationTest extends TestCase
{
    public function test_combined_dot_hot_and_status_effects_are_processed_each_tick(): void
    {
        $example = $this->loadExamples()['combined_example'];

        $runtimeState = $this->buildCombinedRuntimeState($example);
        $runtimeState['player_unit']['current_hp'] = 200;
        $runtimeState['current_wave_index'] = 99;

        $results = [];

        for ($tick = 1; $tick <= 4; $tick++) {
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

        $this->assertSame($example['expected_runtime_results'], $results);
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
     * @param  array<string, mixed>  $example
     * @return array<string, mixed>
     */
    private function buildCombinedRuntimeState(array $example): array
    {
        return $this->buildRuntimeState([
            'battle_id' => 'battle_runtime_dot_hot_status_combined',
            'player_snapshot' => [
                'player_id' => 10001,
                'base_stats' => ['MELEE_ATK' => 120, 'HP' => 300, 'DEF' => 66],
                'bonus_stats' => [],
                'skills' => [],
                'special_effects' => [$example['effects'][1]],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'mon_poison_snake',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 300, 'ATK' => 0, 'DEF' => 10],
                    'skills' => [],
                    'tags' => [],
                    'special_effects' => [$example['effects'][0]],
                ],
                [
                    'monster_id' => 'mon_ice_witch',
                    'wave_index' => 2,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => ['HP' => 300, 'ATK' => 0, 'DEF' => 10],
                    'skills' => [],
                    'tags' => [],
                    'special_effects' => [$example['effects'][2]],
                ],
            ],
            'battle_context' => ['battle_type' => 'main_stage'],
        ]);
    }

    private function buildRuntimeState(array $payload): array
    {
        $buildResult = app(BattleRuntimeStateBuilder::class)->build($payload);

        return $buildResult['data'];
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_minimal_examples_v1.json')), true);
    }
}
