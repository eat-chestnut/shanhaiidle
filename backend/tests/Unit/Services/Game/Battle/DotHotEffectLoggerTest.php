<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DotHotEffectLogger;
use Tests\TestCase;

class DotHotEffectLoggerTest extends TestCase
{
    public function test_it_writes_dot_hot_tick_logs_with_advanced_fields(): void
    {
        $examples = $this->loadExamples();
        $example = $examples['dot_example'];

        $result = app(DotHotEffectLogger::class)->logTick(
            ['logs' => []],
            1,
            $example['owner_unit_id'],
            $example['target_unit_id'],
            $example['effect_key'],
            $example['effect_type'],
            $example['damage_per_tick'],
            0,
            [
                'stack_count' => 2,
                'remaining_ticks' => 4,
                'resisted' => true,
                'resistance_pct' => 0.25,
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'tick' => 1,
            'actor' => 'enemy_mon_poison_1',
            'target' => 'player_10001',
            'action' => 'dot_hot_tick',
            'effect_key' => 'poison_dot',
            'effect_type' => 'dot',
            'hp_damage' => 20,
            'hp_healed' => 0,
            'stack_count' => 2,
            'remaining_ticks' => 4,
            'resisted' => true,
            'resistance_pct' => 0.25,
        ], $result['data']['log']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v2.json')), true);
    }
}
