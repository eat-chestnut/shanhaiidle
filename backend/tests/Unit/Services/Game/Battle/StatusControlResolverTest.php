<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\StatusControlResolver;
use Tests\TestCase;

class StatusControlResolverTest extends TestCase
{
    public function test_advanced_status_control_applies_and_expires_from_json_example(): void
    {
        $example = $this->loadExamples()['status_control_example'];
        $buildResult = app(StatusControlResolver::class)->buildStates([$example]);

        $this->assertTrue($buildResult['ok']);
        $this->assertSame('stunned', $buildResult['data']['status_control_states'][0]['status']);
        $this->assertFalse($buildResult['data']['status_control_states'][0]['stacking']);
        $this->assertSame([], $buildResult['data']['status_control_states'][0]['immune_tags']);
        $this->assertSame(1, $buildResult['data']['status_control_states'][0]['stack_count']);

        $runtimeState = [
            'player_unit' => [
                'unit_id' => 'player_10001',
                'current_hp' => 300,
                'max_hp' => 300,
                'status' => null,
                'alive' => true,
            ],
            'enemy_units' => [
                [
                    'unit_id' => 'enemy_mon_ice_witch_2_1',
                    'current_hp' => 300,
                    'max_hp' => 300,
                    'status' => null,
                    'alive' => true,
                ],
            ],
            'status_control_states' => $buildResult['data']['status_control_states'],
        ];

        $expectedTickResults = [
            ['tick' => 1, 'status_applied' => true, 'remaining_ticks' => 2],
            ['tick' => 2, 'status_active' => true, 'remaining_ticks' => 1],
            ['tick' => 3, 'status_active' => false, 'remaining_ticks' => 0],
        ];

        foreach ($expectedTickResults as $expectedTickResult) {
            $result = app(StatusControlResolver::class)->tick($runtimeState, (int) $expectedTickResult['tick']);

            $this->assertTrue($result['ok']);
            $this->assertSame(
                [$this->buildExpectedTickResult($example, $expectedTickResult)],
                $result['data']['tick_results']
            );

            $runtimeState = $result['data']['runtime_state'];
        }

        $this->assertNull($runtimeState['player_unit']['status']);
    }

    public function test_stacking_status_tracks_multiple_layers(): void
    {
        $example = array_merge(
            $this->loadExamples()['status_control_example'],
            ['stacking' => true, 'refreshable' => true]
        );

        $buildResult = app(StatusControlResolver::class)->buildStates([$example, $example]);

        $this->assertTrue($buildResult['ok']);
        $this->assertCount(1, $buildResult['data']['status_control_states']);
        $this->assertSame(2, $buildResult['data']['status_control_states'][0]['stack_count']);
        $this->assertCount(2, $buildResult['data']['status_control_states'][0]['stacks']);
    }

    public function test_status_immunity_and_duration_resistance_are_applied_from_target_context(): void
    {
        $example = $this->loadExamples()['status_control_example'];

        $immuneBuild = app(StatusControlResolver::class)->buildStates([
            array_merge($example, ['immune_tags' => ['anti_stun']]),
        ], [
            'runtime_state' => [
                'player_unit' => [
                    'unit_id' => 'player_10001',
                    'tags' => ['anti_stun'],
                ],
                'enemy_units' => [],
            ],
        ]);

        $this->assertTrue($immuneBuild['ok']);
        $this->assertSame(0, $immuneBuild['data']['status_control_states'][0]['stack_count']);
        $this->assertTrue($immuneBuild['data']['status_control_states'][0]['stacks'][0]['immune']);

        $resistedBuild = app(StatusControlResolver::class)->buildStates([$example], [
            'runtime_state' => [
                'player_unit' => [
                    'unit_id' => 'player_10001',
                    'runtime_modifiers' => [
                        'cc_duration_down_add_pct' => 0.5,
                    ],
                ],
                'enemy_units' => [],
            ],
        ]);

        $this->assertTrue($resistedBuild['ok']);
        $this->assertSame(1, $resistedBuild['data']['status_control_states'][0]['remaining_ticks']);
        $this->assertTrue($resistedBuild['data']['status_control_states'][0]['resisted']);
        $this->assertSame(0.5, $resistedBuild['data']['status_control_states'][0]['resistance_pct']);
    }

    public function test_dispel_marks_status_as_removed_on_next_tick(): void
    {
        $example = $this->loadExamples()['status_control_example'];
        $resolver = app(StatusControlResolver::class);
        $buildResult = $resolver->buildStates([$example]);

        $runtimeState = [
            'player_unit' => [
                'unit_id' => 'player_10001',
                'current_hp' => 300,
                'max_hp' => 300,
                'status' => null,
                'alive' => true,
            ],
            'enemy_units' => [],
            'status_control_states' => $buildResult['data']['status_control_states'],
        ];

        $dispelResult = $resolver->dispel($runtimeState, 'player_10001', 'stunned', $example['effect_key']);

        $this->assertTrue($dispelResult['ok']);
        $this->assertSame(1, $dispelResult['data']['removed_count']);

        $tickResult = $resolver->tick($dispelResult['data']['runtime_state'], 1);

        $this->assertTrue($tickResult['ok']);
        $this->assertSame([
            [
                'tick' => 1,
                'effect_key' => $example['effect_key'],
                'owner_unit_id' => $example['owner_unit_id'],
                'target_unit_id' => $example['target_unit_id'],
                'status' => $example['status'],
                'stack_count' => 0,
                'remaining_ticks' => 0,
                'status_active' => false,
                'dispelled' => true,
            ],
        ], $tickResult['data']['tick_results']);
        $this->assertNull($tickResult['data']['runtime_state']['player_unit']['status']);
    }

    /**
     * @param  array<string, mixed>  $example
     * @param  array<string, mixed>  $expectedTickResult
     * @return array<string, mixed>
     */
    private function buildExpectedTickResult(array $example, array $expectedTickResult): array
    {
        $result = [
            'tick' => $expectedTickResult['tick'],
            'effect_key' => $example['effect_key'],
            'owner_unit_id' => $example['owner_unit_id'],
            'target_unit_id' => $example['target_unit_id'],
            'status' => $example['status'],
            'stack_count' => ($expectedTickResult['status_active'] ?? null) === false ? 0 : 1,
            'remaining_ticks' => $expectedTickResult['remaining_ticks'],
        ];

        if (array_key_exists('status_applied', $expectedTickResult)) {
            $result['status_applied'] = $expectedTickResult['status_applied'];
        }

        if (array_key_exists('status_active', $expectedTickResult)) {
            $result['status_active'] = $expectedTickResult['status_active'];
        }

        return $result;
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v2.json')), true);
    }
}
