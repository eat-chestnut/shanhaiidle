<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\SpecialEffectTriggerResolver;
use Tests\TestCase;

class SpecialEffectTriggerResolverTest extends TestCase
{
    public function test_it_resolves_passive_always_runtime_effects(): void
    {
        $runtimeEffects = $this->loadExamples()['runtime_effect_state_example']['expected_runtime_effects'];

        $result = app(SpecialEffectTriggerResolver::class)->resolveByTiming($runtimeEffects, 'passive_always');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertCount(2, $result['data']['runtime_effects']);
        $this->assertSame('boss_hunt_tag', $result['data']['runtime_effects'][0]['effect_key']);
        $this->assertSame('always_bonus_def_flat', $result['data']['runtime_effects'][1]['effect_key']);
    }

    public function test_it_resolves_on_battle_start_runtime_effects(): void
    {
        $runtimeEffects = $this->loadExamples()['runtime_effect_state_example']['expected_runtime_effects'];

        $result = app(SpecialEffectTriggerResolver::class)->resolveByTiming($runtimeEffects, 'on_battle_start');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            $runtimeEffects[1],
        ], $result['data']['runtime_effects']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/buff_special_effect_execution_minimal_examples_v1.json')), true);
    }
}
