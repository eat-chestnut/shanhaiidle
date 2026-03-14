<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\RuntimeEffectStateBuilder;
use Tests\TestCase;

class RuntimeEffectStateBuilderTest extends TestCase
{
    public function test_it_builds_runtime_effects_from_special_effects(): void
    {
        $examples = $this->loadExamples();
        $example = $examples['runtime_effect_state_example'];

        $result = app(RuntimeEffectStateBuilder::class)->build(
            $example['unit_id'],
            $example['special_effects']
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(['runtime_effects'], array_keys($result['data']));
        $this->assertSame($example['expected_runtime_effects'], $result['data']['runtime_effects']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/buff_special_effect_execution_minimal_examples_v1.json')), true);
    }
}
