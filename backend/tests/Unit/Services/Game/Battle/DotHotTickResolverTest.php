<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DotHotStateBuilder;
use App\Services\Game\Battle\DotHotTickResolver;
use Tests\TestCase;

class DotHotTickResolverTest extends TestCase
{
    public function test_dot_deals_damage_per_tick_from_advanced_json_example(): void
    {
        $example = $this->loadExamples()['dot_example'];
        $runtimeState = $this->buildRuntimeState([$example], 200, 200);

        for ($tick = 1; $tick <= (int) $example['duration_ticks']; $tick++) {
            $result = app(DotHotTickResolver::class)->resolve($runtimeState, $tick);

            $this->assertTrue($result['ok']);
            $this->assertSame([
                [
                    'tick' => $tick,
                    'effect_key' => $example['effect_key'],
                    'owner_unit_id' => $example['owner_unit_id'],
                    'target_unit_id' => $example['target_unit_id'],
                    'effect_type' => $example['effect_type'],
                    'hp_damage' => $example['damage_per_tick'],
                    'hp_healed' => 0,
                    'stack_count' => 1,
                    'remaining_ticks' => (int) $example['duration_ticks'] - $tick + 1,
                ],
            ], $result['data']['tick_results']);

            $runtimeState = $result['data']['runtime_state'];
        }

        $this->assertSame(100, $runtimeState['player_unit']['current_hp']);
    }

    public function test_hot_restores_hp_per_tick_from_advanced_json_example(): void
    {
        $example = $this->loadExamples()['hot_example'];
        $runtimeState = $this->buildRuntimeState([$example], 100, 300);

        for ($tick = 1; $tick <= (int) $example['duration_ticks']; $tick++) {
            $result = app(DotHotTickResolver::class)->resolve($runtimeState, $tick);

            $this->assertTrue($result['ok']);
            $this->assertSame([
                [
                    'tick' => $tick,
                    'effect_key' => $example['effect_key'],
                    'owner_unit_id' => $example['owner_unit_id'],
                    'target_unit_id' => $example['target_unit_id'],
                    'effect_type' => $example['effect_type'],
                    'hp_damage' => 0,
                    'hp_healed' => $example['healing_per_tick'],
                    'stack_count' => 1,
                    'remaining_ticks' => (int) $example['duration_ticks'] - $tick + 1,
                ],
            ], $result['data']['tick_results']);

            $runtimeState = $result['data']['runtime_state'];
        }

        $this->assertSame(250, $runtimeState['player_unit']['current_hp']);
    }

    public function test_stacked_dot_damage_is_summed_each_tick(): void
    {
        $example = $this->loadExamples()['dot_example'];
        $runtimeState = $this->buildRuntimeState([$example, $example], 200, 200);

        $firstTick = app(DotHotTickResolver::class)->resolve($runtimeState, 1);

        $this->assertTrue($firstTick['ok']);
        $this->assertSame(40, $firstTick['data']['tick_results'][0]['hp_damage']);
        $this->assertSame(2, $firstTick['data']['tick_results'][0]['stack_count']);
        $this->assertSame(160, $firstTick['data']['runtime_state']['player_unit']['current_hp']);
    }

    public function test_dispel_marks_dot_state_and_stops_future_tick_damage(): void
    {
        $example = $this->loadExamples()['dot_example'];
        $resolver = app(DotHotTickResolver::class);
        $runtimeState = $this->buildRuntimeState([$example], 200, 200);

        $dispelResult = $resolver->dispel($runtimeState, 'player_10001', 'dot', $example['effect_key']);

        $this->assertTrue($dispelResult['ok']);
        $this->assertSame(1, $dispelResult['data']['removed_count']);

        $tickResult = $resolver->resolve($dispelResult['data']['runtime_state'], 1);

        $this->assertTrue($tickResult['ok']);
        $this->assertSame([
            [
                'tick' => 1,
                'effect_key' => $example['effect_key'],
                'owner_unit_id' => $example['owner_unit_id'],
                'target_unit_id' => $example['target_unit_id'],
                'effect_type' => $example['effect_type'],
                'hp_damage' => 0,
                'hp_healed' => 0,
                'stack_count' => 0,
                'remaining_ticks' => 0,
                'dispelled' => true,
            ],
        ], $tickResult['data']['tick_results']);
        $this->assertSame(200, $tickResult['data']['runtime_state']['player_unit']['current_hp']);
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
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v2.json')), true);
    }
}
