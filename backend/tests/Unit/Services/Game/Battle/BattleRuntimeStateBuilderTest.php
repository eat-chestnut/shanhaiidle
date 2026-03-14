<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleRuntimeStateBuilder;
use Tests\TestCase;

class BattleRuntimeStateBuilderTest extends TestCase
{
    public function test_it_builds_runtime_state_from_battle_start_payload(): void
    {
        $examples = $this->loadExamples();

        $result = app(BattleRuntimeStateBuilder::class)->build($examples['battle_start_payload_example']);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame(
            ['battle_id', 'status', 'tick', 'current_wave_index', 'player_unit', 'enemy_units', 'battle_context', 'logs'],
            array_keys($result['data'])
        );
        $this->assertSame('running', $result['data']['status']);
        $this->assertSame(0, $result['data']['tick']);
        $this->assertSame(1, $result['data']['current_wave_index']);
        $this->assertSame('player_10001', $result['data']['player_unit']['unit_id']);
        $this->assertSame('player', $result['data']['player_unit']['side']);
        $this->assertSame(850, $result['data']['player_unit']['current_hp']);
        $this->assertSame(850, $result['data']['player_unit']['max_hp']);
        $this->assertSame($examples['battle_start_payload_example']['player_snapshot']['base_stats'], $result['data']['player_unit']['stats']);
        $this->assertSame($examples['battle_start_payload_example']['player_snapshot']['bonus_stats'], $result['data']['player_unit']['bonus_stats']);
        $this->assertSame($examples['battle_start_payload_example']['player_snapshot']['special_effects'], $result['data']['player_unit']['special_effects']);
        $this->assertTrue($result['data']['player_unit']['alive']);
        $this->assertCount(3, $result['data']['enemy_units']);
        $this->assertSame([
            'unit_id' => 'enemy_mon_qingqiu_guard_1_1',
            'monster_id' => 'mon_qingqiu_guard',
            'side' => 'enemy',
            'wave_index' => 1,
            'unit_index' => 1,
            'is_boss' => false,
            'current_hp' => 500,
            'max_hp' => 500,
            'stats' => [
                'HP' => 500,
                'ATK' => 40,
                'DEF' => 12,
            ],
            'skills' => ['skill_claw'],
            'tags' => ['melee', 'beast'],
            'alive' => true,
        ], $result['data']['enemy_units'][0]);
        $this->assertSame($examples['battle_start_payload_example']['battle_context'], $result['data']['battle_context']);
        $this->assertSame([], $result['data']['logs']);
        $this->assertStringStartsWith('battle_runtime_10001_', $result['data']['battle_id']);
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/combat_runtime_minimal_loop_examples_v1.json')), true);
    }
}
