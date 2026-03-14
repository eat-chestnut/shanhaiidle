<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DotHotStateBuilder;
use App\Services\Game\Battle\DotHotTickResolver;
use Tests\TestCase;

class DotHotTickResolverTest extends TestCase
{
    public function test_dot_deals_damage_per_tick_from_json_example(): void
    {
        $example = $this->loadExamples()['dot_example'];
        $runtimeState = $this->buildRuntimeState([$example], 200, 200);

        foreach ($example['expected_tick_results'] as $expectedTickResult) {
            $result = app(DotHotTickResolver::class)->resolve($runtimeState, (int) $expectedTickResult['tick']);

            $this->assertTrue($result['ok']);
            $this->assertSame([
                [
                    'tick' => $expectedTickResult['tick'],
                    'effect_key' => $example['effect_key'],
                    'owner_unit_id' => $example['owner_unit_id'],
                    'target_unit_id' => $example['target_unit_id'],
                    'effect_type' => $example['effect_type'],
                    'hp_damage' => $expectedTickResult['hp_damage'],
                    'hp_healed' => 0,
                ],
            ], $result['data']['tick_results']);

            $runtimeState = $result['data']['runtime_state'];
        }

        $this->assertSame(50, $runtimeState['player_unit']['current_hp']);
    }

    public function test_hot_restores_hp_per_tick_from_json_example(): void
    {
        $example = $this->loadExamples()['hot_example'];
        $runtimeState = $this->buildRuntimeState([$example], 100, 300);

        foreach ($example['expected_tick_results'] as $expectedTickResult) {
            $result = app(DotHotTickResolver::class)->resolve($runtimeState, (int) $expectedTickResult['tick']);

            $this->assertTrue($result['ok']);
            $this->assertSame([
                [
                    'tick' => $expectedTickResult['tick'],
                    'effect_key' => $example['effect_key'],
                    'owner_unit_id' => $example['owner_unit_id'],
                    'target_unit_id' => $example['target_unit_id'],
                    'effect_type' => $example['effect_type'],
                    'hp_damage' => 0,
                    'hp_healed' => $expectedTickResult['hp_healed'],
                ],
            ], $result['data']['tick_results']);

            $runtimeState = $result['data']['runtime_state'];
        }

        $this->assertSame(250, $runtimeState['player_unit']['current_hp']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $effects
     * @return array<string, mixed>
     */
    private function buildRuntimeState(array $effects, int $currentHp, int $maxHp): array
    {
        $buildResult = app(DotHotStateBuilder::class)->build($effects);

        return [
            'tick' => 0,
            'player_unit' => [
                'unit_id' => 'player_10001',
                'current_hp' => $currentHp,
                'max_hp' => $maxHp,
                'alive' => true,
            ],
            'enemy_units' => [
                [
                    'unit_id' => 'enemy_mon_fire_drake_1_1',
                    'current_hp' => 300,
                    'max_hp' => 300,
                    'alive' => true,
                ],
            ],
            'dot_hot_states' => $buildResult['data']['dot_hot_states'],
        ];
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_minimal_examples_v1.json')), true);
    }
}
