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

    public function test_it_builds_runtime_state_for_multi_skill_types(): void
    {
        $result = app(SkillRuntimeStateBuilder::class)->build(
            ['unit_id' => 'player_10001'],
            [
                [
                    'skill_id' => 'skill_double_slash',
                    'skill_type' => 'multi_hit',
                    'multi_hit_count' => 2,
                    'hit_damage_ratios' => [0.85, 0.808333333],
                    'cooldown_total' => 3,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
                [
                    'skill_id' => 'skill_fire_blast',
                    'skill_type' => 'aoe',
                    'damage_ratio' => 0.725,
                    'cooldown_total' => 3,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
                [
                    'skill_id' => 'skill_atk_boost',
                    'skill_type' => 'self_buff',
                    'modifier_key' => 'bonus_atk_percent',
                    'modifier_value' => 20,
                    'cooldown_total' => 4,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
                [
                    'skill_id' => 'skill_guard_up',
                    'skill_type' => 'shield',
                    'shield_value' => 150,
                    'cooldown_total' => 4,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('multi_hit', $result['data']['skills'][0]['skill_type']);
        $this->assertSame([0.85, 0.808333333], $result['data']['skills'][0]['hit_damage_ratios']);
        $this->assertSame('aoe', $result['data']['skills'][1]['skill_type']);
        $this->assertSame(0.725, $result['data']['skills'][1]['damage_ratio']);
        $this->assertSame('bonus_atk_percent', $result['data']['skills'][2]['modifier_key']);
        $this->assertSame(20, $result['data']['skills'][2]['modifier_value']);
        $this->assertSame(150, $result['data']['skills'][3]['shield_value']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/skill_execution_minimal_examples_v1.json')), true);
    }
}
