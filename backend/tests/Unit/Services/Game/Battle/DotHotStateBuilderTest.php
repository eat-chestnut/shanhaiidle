<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DotHotStateBuilder;
use Tests\TestCase;

class DotHotStateBuilderTest extends TestCase
{
    public function test_it_builds_dot_and_hot_runtime_states_from_json_examples(): void
    {
        $examples = $this->loadExamples();

        $result = app(DotHotStateBuilder::class)->build([
            $examples['dot_example'],
            $examples['hot_example'],
            $examples['status_control_example'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            [
                'effect_key' => 'burning_dot',
                'owner_unit_id' => 'enemy_mon_fire_drake_1_1',
                'target_unit_id' => 'player_10001',
                'effect_type' => 'dot',
                'trigger_timing' => 'per_tick',
                'duration_ticks' => 5,
                'damage_per_tick' => 30,
                'remaining_ticks' => 5,
            ],
            [
                'effect_key' => 'regeneration_hot',
                'owner_unit_id' => 'player_10001',
                'target_unit_id' => 'player_10001',
                'effect_type' => 'hot',
                'trigger_timing' => 'per_tick',
                'duration_ticks' => 3,
                'healing_per_tick' => 50,
                'remaining_ticks' => 3,
            ],
        ], $result['data']['dot_hot_states']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_minimal_examples_v1.json')), true);
    }
}
