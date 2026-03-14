<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleRuntimeStateBuilder;
use Tests\TestCase;

class BattleRuntimeStateBuilderTest extends TestCase
{
    public function test_it_builds_runtime_state_from_battle_start_payload(): void
    {
        $examples = $this->loadExamples();

        $result = app(BattleRuntimeStateBuilder::class)->build($examples['battle_start_payload_example']);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(
            ['battle_id', 'status', 'tick', 'current_wave_index', 'player_unit', 'enemy_units', 'battle_context', 'dot_hot_states', 'status_control_states', 'logs'],
            array_keys($result['data'])
        );
        $this->assertSame('running', $result['data']['status']);
        $this->assertSame(0, $result['data']['tick']);
        $this->assertSame(1, $result['data']['current_wave_index']);
        $this->assertSame('player_10001', $result['data']['player_unit']['unit_id']);
        $this->assertSame('player', $result['data']['player_unit']['side']);
        $this->assertSame(850, $result['data']['player_unit']['current_hp']);
        $this->assertSame(850, $result['data']['player_unit']['max_hp']);
        $this->assertSame($examples['battle_start_payload_example']['player_snapshot']['base_stats'], $result['data']['player_unit']['stats']);
        $this->assertSame($examples['battle_start_payload_example']['player_snapshot']['bonus_stats'], $result['data']['player_unit']['bonus_stats']);
        $this->assertSame($examples['battle_start_payload_example']['player_snapshot']['special_effects'], $result['data']['player_unit']['special_effects']);
        $this->assertSame([], $result['data']['player_unit']['runtime_effects']);
        $this->assertSame([], $result['data']['player_unit']['runtime_modifiers']);
        $this->assertSame([], $result['data']['player_unit']['runtime_tags']);
        $this->assertSame(0, $result['data']['player_unit']['shield']);
        $this->assertNull($result['data']['player_unit']['status']);
        $this->assertTrue($result['data']['player_unit']['alive']);
        $this->assertCount(3, $result['data']['enemy_units']);
        $this->assertSame([
            'unit_id' => 'enemy_mon_qingqiu_guard_1_1',
            'monster_id' => 'mon_qingqiu_guard',
            'side' => 'enemy',
            'wave_index' => 1,
            'unit_index' => 1,
            'is_boss' => false,
            'current_hp' => 500,
            'max_hp' => 500,
            'stats' => [
                'HP' => 500,
                'ATK' => 40,
                'DEF' => 12,
            ],
            'skills' => ['skill_claw'],
            'tags' => ['melee', 'beast'],
            'runtime_effects' => [],
            'runtime_modifiers' => [],
            'runtime_tags' => [],
            'shield' => 0,
            'status' => null,
            'alive' => true,
        ], $result['data']['enemy_units'][0]);
        $this->assertSame($examples['battle_start_payload_example']['battle_context'], $result['data']['battle_context']);
        $this->assertSame([], $result['data']['dot_hot_states']);
        $this->assertSame([], $result['data']['status_control_states']);
        $this->assertSame([], $result['data']['logs']);
        $this->assertStringStartsWith('battle_runtime_10001_', $result['data']['battle_id']);
    }

    public function test_it_initializes_minimal_special_effect_runtime_during_build(): void
    {
        $buffExamples = $this->loadBuffExamples();
        $runtimeExample = $buffExamples['runtime_effect_state_example'];

        $result = app(BattleRuntimeStateBuilder::class)->build([
            'battle_id' => 'battle_runtime_001',
            'player_snapshot' => [
                'player_id' => 10001,
                'base_stats' => [
                    'MELEE_ATK' => 120,
                    'HP' => 850,
                    'DEF' => 66,
                ],
                'bonus_stats' => [],
                'skills' => [],
                'special_effects' => $runtimeExample['special_effects'],
            ],
            'enemy_snapshots' => [],
            'battle_context' => [
                'battle_type' => 'main_stage',
            ],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $expectedRuntimeEffects = $runtimeExample['expected_runtime_effects'];
        $expectedRuntimeEffects[1]['enabled'] = false;

        $this->assertSame($expectedRuntimeEffects, $result['data']['player_unit']['runtime_effects']);
        $this->assertSame(
            $buffExamples['passive_apply_example']['after']['runtime_tags'],
            $result['data']['player_unit']['runtime_tags']
        );
        $this->assertSame(
            $buffExamples['passive_apply_example']['after']['runtime_modifiers'],
            $result['data']['player_unit']['runtime_modifiers']
        );
        $this->assertSame(
            $buffExamples['battle_start_apply_example']['after']['shield'],
            $result['data']['player_unit']['shield']
        );
        $this->assertSame([
            [
                'tick' => 0,
                'actor' => 'player_10001',
                'action' => 'effect_apply',
                'effect_key' => 'boss_hunt_tag',
                'value' => null,
                'source' => 'core_qingqiu_frost',
            ],
            [
                'tick' => 0,
                'actor' => 'player_10001',
                'action' => 'effect_apply',
                'effect_key' => 'always_bonus_def_flat',
                'value' => 15,
                'source' => 'set_zhaoyao_40_4pc',
            ],
            $buffExamples['effect_log_example'],
        ], $result['data']['logs']);
    }

    public function test_it_initializes_dot_hot_and_status_control_runtime_during_build(): void
    {
        $examples = $this->loadDotHotExamples();

        $result = app(BattleRuntimeStateBuilder::class)->build([
            'battle_id' => 'battle_runtime_dot_hot_001',
            'player_snapshot' => [
                'player_id' => 10001,
                'base_stats' => [
                    'MELEE_ATK' => 120,
                    'HP' => 300,
                    'DEF' => 66,
                ],
                'bonus_stats' => [],
                'skills' => [],
                'special_effects' => [
                    $examples['hot_example'],
                ],
            ],
            'enemy_snapshots' => [
                [
                    'monster_id' => 'mon_fire_drake',
                    'wave_index' => 1,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => [
                        'HP' => 300,
                        'ATK' => 0,
                        'DEF' => 10,
                    ],
                    'skills' => [],
                    'tags' => [],
                    'special_effects' => [
                        $examples['dot_example'],
                    ],
                ],
                [
                    'monster_id' => 'mon_ice_witch',
                    'wave_index' => 2,
                    'unit_index' => 1,
                    'is_boss' => false,
                    'base_stats' => [
                        'HP' => 300,
                        'ATK' => 0,
                        'DEF' => 10,
                    ],
                    'skills' => [],
                    'tags' => [],
                    'special_effects' => [
                        $examples['status_control_example'],
                    ],
                ],
            ],
            'battle_context' => [
                'battle_type' => 'main_stage',
            ],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['data']['dot_hot_states']);
        $this->assertCount(1, $result['data']['status_control_states']);
        $this->assertNull($result['data']['player_unit']['status']);

        $dotHotStates = [];
        foreach ($result['data']['dot_hot_states'] as $state) {
            $dotHotStates[$state['effect_key']] = $state;
        }

        $this->assertTrue($dotHotStates['poison_dot']['stacking']);
        $this->assertTrue($dotHotStates['poison_dot']['refreshable']);
        $this->assertSame([], $dotHotStates['poison_dot']['immune_tags']);
        $this->assertSame(20, $dotHotStates['poison_dot']['damage_per_tick']);
        $this->assertSame(1, $dotHotStates['poison_dot']['stack_count']);
        $this->assertCount(1, $dotHotStates['poison_dot']['stacks']);
        $this->assertTrue($dotHotStates['regeneration_hot']['stacking']);
        $this->assertFalse($dotHotStates['regeneration_hot']['refreshable']);
        $this->assertSame(50, $dotHotStates['regeneration_hot']['healing_per_tick']);
        $this->assertSame(1, $dotHotStates['regeneration_hot']['stack_count']);
        $this->assertCount(1, $dotHotStates['regeneration_hot']['stacks']);

        $statusState = $result['data']['status_control_states'][0];
        $this->assertSame('stunned', $statusState['status']);
        $this->assertSame(2, $statusState['remaining_ticks']);
        $this->assertFalse($statusState['stacking']);
        $this->assertSame([], $statusState['immune_tags']);
        $this->assertSame(1, $statusState['stack_count']);
        $this->assertCount(1, $statusState['stacks']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/combat_runtime_minimal_loop_examples_v1.json')), true);
    }

    private function loadBuffExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/buff_special_effect_execution_minimal_examples_v1.json')), true);
    }

    private function loadDotHotExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v2.json')), true);
    }
}
