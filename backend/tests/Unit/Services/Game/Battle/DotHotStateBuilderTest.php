<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DotHotStateBuilder;
use App\Services\Game\Battle\DotHotTickResolver;
use Tests\TestCase;

class DotHotStateBuilderTest extends TestCase
{
    public function test_it_builds_advanced_dot_and_hot_runtime_states_from_json_examples(): void
    {
        $examples = $this->loadExamples();

        $result = app(DotHotStateBuilder::class)->build([
            $examples['dot_example'],
            $examples['hot_example'],
            $examples['status_control_example'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['data']['dot_hot_states']);

        $dotState = $result['data']['dot_hot_states'][0];
        $hotState = $result['data']['dot_hot_states'][1];

        $this->assertSame('poison_dot', $dotState['effect_key']);
        $this->assertSame('dot', $dotState['effect_type']);
        $this->assertTrue($dotState['stacking']);
        $this->assertTrue($dotState['refreshable']);
        $this->assertSame([], $dotState['immune_tags']);
        $this->assertSame(20, $dotState['damage_per_tick']);
        $this->assertSame(1, $dotState['stack_count']);
        $this->assertSame(5, $dotState['remaining_ticks']);
        $this->assertCount(1, $dotState['stacks']);

        $this->assertSame('regeneration_hot', $hotState['effect_key']);
        $this->assertSame('hot', $hotState['effect_type']);
        $this->assertTrue($hotState['stacking']);
        $this->assertFalse($hotState['refreshable']);
        $this->assertSame(50, $hotState['healing_per_tick']);
        $this->assertSame(1, $hotState['stack_count']);
        $this->assertSame(3, $hotState['remaining_ticks']);
        $this->assertCount(1, $hotState['stacks']);
    }

    public function test_it_merges_stacking_and_refreshable_dot_effects_into_runtime_state(): void
    {
        $dotEffect = $this->loadExamples()['dot_example'];

        $initialBuild = app(DotHotStateBuilder::class)->build([$dotEffect, $dotEffect]);

        $this->assertTrue($initialBuild['ok']);
        $this->assertCount(1, $initialBuild['data']['dot_hot_states']);
        $this->assertSame(40, $initialBuild['data']['dot_hot_states'][0]['damage_per_tick']);
        $this->assertSame(2, $initialBuild['data']['dot_hot_states'][0]['stack_count']);
        $this->assertCount(2, $initialBuild['data']['dot_hot_states'][0]['stacks']);

        $tickResult = app(DotHotTickResolver::class)->resolve([
            'tick' => 0,
            'player_unit' => [
                'unit_id' => 'player_10001',
                'current_hp' => 300,
                'max_hp' => 300,
                'alive' => true,
            ],
            'enemy_units' => [],
            'dot_hot_states' => $initialBuild['data']['dot_hot_states'],
        ], 1);

        $this->assertTrue($tickResult['ok']);
        $this->assertSame(4, $tickResult['data']['runtime_state']['dot_hot_states'][0]['remaining_ticks']);

        $refreshBuild = app(DotHotStateBuilder::class)->build([$dotEffect], [
            'existing_states' => $tickResult['data']['runtime_state']['dot_hot_states'],
            'tick' => 1,
        ]);

        $this->assertTrue($refreshBuild['ok']);
        $this->assertCount(1, $refreshBuild['data']['dot_hot_states']);
        $this->assertSame(60, $refreshBuild['data']['dot_hot_states'][0]['damage_per_tick']);
        $this->assertSame(3, $refreshBuild['data']['dot_hot_states'][0]['stack_count']);
        $this->assertSame(5, $refreshBuild['data']['dot_hot_states'][0]['remaining_ticks']);
        $this->assertSame([5, 5, 5], array_map(
            static fn (array $stack): int => $stack['remaining_ticks'],
            $refreshBuild['data']['dot_hot_states'][0]['stacks']
        ));
    }

    public function test_it_applies_immunity_and_resistance_from_target_context(): void
    {
        $dotEffect = $this->loadExamples()['dot_example'];

        $resistedBuild = app(DotHotStateBuilder::class)->build([
            array_merge($dotEffect, ['immune_tags' => []]),
        ], [
            'runtime_state' => [
                'player_unit' => [
                    'unit_id' => 'player_10001',
                    'runtime_modifiers' => [
                        'dot_resist_pct' => 0.25,
                        'dot_duration_down_pct' => 0.4,
                    ],
                ],
                'enemy_units' => [],
            ],
        ]);

        $this->assertTrue($resistedBuild['ok']);
        $resistedState = $resistedBuild['data']['dot_hot_states'][0];
        $this->assertSame(15, $resistedState['damage_per_tick']);
        $this->assertSame(3, $resistedState['remaining_ticks']);
        $this->assertTrue($resistedState['resisted']);
        $this->assertSame(0.4, $resistedState['resistance_pct']);

        $immuneBuild = app(DotHotStateBuilder::class)->build([
            array_merge($dotEffect, ['immune_tags' => ['poison_immune']]),
        ], [
            'runtime_state' => [
                'player_unit' => [
                    'unit_id' => 'player_10001',
                    'tags' => ['poison_immune'],
                ],
                'enemy_units' => [],
            ],
        ]);

        $this->assertTrue($immuneBuild['ok']);
        $immuneState = $immuneBuild['data']['dot_hot_states'][0];
        $this->assertSame(0, $immuneState['stack_count']);
        $this->assertSame(0, $immuneState['remaining_ticks']);
        $this->assertTrue($immuneState['stacks'][0]['immune']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v2.json')), true);
    }
}
