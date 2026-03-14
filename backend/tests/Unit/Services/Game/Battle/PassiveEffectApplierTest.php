<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\PassiveEffectApplier;
use Tests\TestCase;

class PassiveEffectApplierTest extends TestCase
{
    public function test_passive_tag_is_written_to_runtime_tags(): void
    {
        $examples = $this->loadExamples();
        $runtimeEffects = $examples['runtime_effect_state_example']['expected_runtime_effects'];
        $passiveTagEffect = [$runtimeEffects[0]];
        $unitRuntimeState = $examples['passive_apply_example']['before'];
        $unitRuntimeState['runtime_effects'] = $runtimeEffects;

        $result = app(PassiveEffectApplier::class)->apply($unitRuntimeState, $passiveTagEffect);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(['boss_hunt_tag'], $result['data']['unit_runtime_state']['runtime_tags']);
        $this->assertSame(['bonus_def_flat' => 0], $result['data']['unit_runtime_state']['runtime_modifiers']);
        $this->assertSame(0, $result['data']['unit_runtime_state']['shield']);
    }

    public function test_passive_modifier_is_written_to_runtime_modifiers(): void
    {
        $examples = $this->loadExamples();
        $runtimeEffects = $examples['runtime_effect_state_example']['expected_runtime_effects'];
        $passiveModifierEffect = [$runtimeEffects[2]];
        $unitRuntimeState = $examples['passive_apply_example']['before'];
        $unitRuntimeState['runtime_effects'] = $runtimeEffects;

        $result = app(PassiveEffectApplier::class)->apply($unitRuntimeState, $passiveModifierEffect);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([], $result['data']['unit_runtime_state']['runtime_tags']);
        $this->assertSame(['bonus_def_flat' => 15], $result['data']['unit_runtime_state']['runtime_modifiers']);
        $this->assertSame([$runtimeEffects[2]], $result['data']['applied_effects']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/buff_special_effect_execution_minimal_examples_v1.json')), true);
    }
}
