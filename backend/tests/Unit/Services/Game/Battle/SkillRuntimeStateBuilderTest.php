<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\SkillRuntimeStateBuilder;
use Tests\TestCase;

class SkillRuntimeStateBuilderTest extends TestCase
{
    public function test_it_builds_runtime_skill_state(): void
    {
        $examples = $this->loadExamples();
        $runtimeExample = $examples['skill_runtime_state_example'];

        $result = app(SkillRuntimeStateBuilder::class)->build(
            ['unit_id' => $runtimeExample['unit_id']],
            $runtimeExample['skills'],
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame($runtimeExample['skills'], $result['data']['skills']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/skill_execution_minimal_examples_v1.json')), true);
    }
}
