<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DotHotEffectLogger;
use Tests\TestCase;

class DotHotEffectLoggerTest extends TestCase
{
    public function test_it_writes_dot_hot_tick_logs(): void
    {
        $examples = $this->loadExamples();
        $example = $examples['dot_example'];
        $expectedTickResult = $example['expected_tick_results'][0];

        $result = app(DotHotEffectLogger::class)->logTick(
            ['logs' => []],
            (int) $expectedTickResult['tick'],
            $example['owner_unit_id'],
            $example['target_unit_id'],
            $example['effect_key'],
            $example['effect_type'],
            $expectedTickResult['hp_damage'],
            0
        );

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'tick' => 1,
            'actor' => 'enemy_mon_fire_drake_1_1',
            'target' => 'player_10001',
            'action' => 'dot_hot_tick',
            'effect_key' => 'burning_dot',
            'effect_type' => 'dot',
            'hp_damage' => 30,
            'hp_healed' => 0,
        ], $result['data']['log']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_minimal_examples_v1.json')), true);
    }
}
