<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\DamageActionLogger;
use Tests\TestCase;

class DamageActionLoggerTest extends TestCase
{
    public function test_it_writes_damage_logs_with_expanded_fields(): void
    {
        $example = $this->loadExamples()['damage_log_example'];

        $result = app(DamageActionLogger::class)->logDamage(
            [
                'battle_id' => 'battle_runtime_001',
                'logs' => [],
            ],
            $example['tick'],
            $example['actor'],
            $example['target'],
            $example['action'],
            $example['skill_id'],
            $example['raw_damage'],
            $example['is_critical'],
            $example['shield_absorbed'],
            $example['hp_damage'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example, $result['data']['log']);
        $this->assertSame([$example], $result['data']['runtime_state']['logs']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/damage_formula_expansion_examples_v1.json')), true);
    }
}
