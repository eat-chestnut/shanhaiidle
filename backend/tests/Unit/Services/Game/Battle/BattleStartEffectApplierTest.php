<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleStartEffectApplier;
use Tests\TestCase;

class BattleStartEffectApplierTest extends TestCase
{
    public function test_battle_start_shield_is_written_to_shield(): void
    {
        $examples = $this->loadExamples();
        $shieldEffect = $examples['runtime_effect_state_example']['expected_runtime_effects'][1];
        $unitRuntimeState = $examples['battle_start_apply_example']['before'];
        $unitRuntimeState['runtime_effects'] = [$shieldEffect];

        $result = app(BattleStartEffectApplier::class)->apply($unitRuntimeState, [$shieldEffect]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(120, $result['data']['unit_runtime_state']['shield']);
        $this->assertFalse($result['data']['unit_runtime_state']['runtime_effects'][0]['enabled']);
    }

    public function test_battle_start_modifier_is_written_to_runtime_modifiers(): void
    {
        $battleStartModifier = [
            'effect_key' => 'battle_start_bonus_atk_flat',
            'owner_unit_id' => 'player_10001',
            'source' => 'set_zhaoyao_40_2pc',
            'effect_type' => 'battle_start_modifier',
            'trigger_timing' => 'on_battle_start',
            'value' => 20,
            'modifier_key' => 'bonus_atk_flat',
            'enabled' => true,
        ];

        $result = app(BattleStartEffectApplier::class)->apply([
            'unit_id' => 'player_10001',
            'runtime_effects' => [$battleStartModifier],
            'runtime_tags' => [],
            'runtime_modifiers' => [],
            'shield' => 0,
        ], [$battleStartModifier]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(['bonus_atk_flat' => 20], $result['data']['unit_runtime_state']['runtime_modifiers']);
        $this->assertFalse($result['data']['unit_runtime_state']['runtime_effects'][0]['enabled']);
    }

    public function test_battle_start_effects_only_apply_once(): void
    {
        $examples = $this->loadExamples();
        $shieldEffect = $examples['runtime_effect_state_example']['expected_runtime_effects'][1];
        $unitRuntimeState = $examples['battle_start_apply_example']['before'];
        $unitRuntimeState['runtime_effects'] = [$shieldEffect];

        $firstApply = app(BattleStartEffectApplier::class)->apply($unitRuntimeState, [$shieldEffect]);
        $secondApply = app(BattleStartEffectApplier::class)->apply(
            $firstApply['data']['unit_runtime_state'],
            [$shieldEffect]
        );

        $this->assertTrue($firstApply['ok']);
        $this->assertTrue($secondApply['ok']);
        $this->assertSame(120, $secondApply['data']['unit_runtime_state']['shield']);
        $this->assertSame([], $secondApply['data']['applied_effects']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/buff_special_effect_execution_minimal_examples_v1.json')), true);
    }
}
