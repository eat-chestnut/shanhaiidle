<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\EffectActionLogger;
use Tests\TestCase;

class EffectActionLoggerTest extends TestCase
{
    public function test_it_writes_effect_apply_logs(): void
    {
        $example = $this->loadExamples()['effect_log_example'];

        $result = app(EffectActionLogger::class)->logEffectApply(
            [
                'battle_id' => 'battle_runtime_001',
                'logs' => [],
            ],
            $example['tick'],
            $example['actor'],
            $example['effect_key'],
            $example['value'],
            $example['source']
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame($example, $result['data']['log']);
        $this->assertSame([$example], $result['data']['runtime_state']['logs']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/buff_special_effect_execution_minimal_examples_v1.json')), true);
    }
}
