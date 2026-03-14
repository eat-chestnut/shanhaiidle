<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\StatusEffectLogger;
use Tests\TestCase;

class StatusEffectLoggerTest extends TestCase
{
    public function test_it_writes_status_effect_logs_with_advanced_fields(): void
    {
        $examples = $this->loadExamples();
        $example = $examples['status_control_example'];

        $result = app(StatusEffectLogger::class)->logStatus(
            ['logs' => []],
            1,
            $example['owner_unit_id'],
            $example['target_unit_id'],
            $example['effect_key'],
            $example['status'],
            true,
            null,
            [
                'stack_count' => 1,
                'remaining_ticks' => 2,
                'resisted' => true,
                'resistance_pct' => 0.5,
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame([
            'tick' => 1,
            'actor' => 'enemy_mon_ice_witch_2_1',
            'target' => 'player_10001',
            'action' => 'status_effect',
            'effect_key' => 'stun_status',
            'status' => 'stunned',
            'status_applied' => true,
            'stack_count' => 1,
            'remaining_ticks' => 2,
            'resisted' => true,
            'resistance_pct' => 0.5,
        ], $result['data']['log']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v2.json')), true);
    }
}
