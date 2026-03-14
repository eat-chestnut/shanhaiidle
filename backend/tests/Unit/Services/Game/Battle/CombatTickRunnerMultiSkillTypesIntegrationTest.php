<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\CombatTickRunner;
use Tests\TestCase;

class CombatTickRunnerMultiSkillTypesIntegrationTest extends TestCase
{
    public function test_multi_hit_skill_logs_each_hit_separately(): void
    {
        $example = $this->loadExamples()['multi_hit_example'];

        $runtimeState = $this->buildRuntimeState(
            $this->buildPlayerUnit($example['attacker_unit'], [$example['skill_state']]),
            [$this->buildEnemyUnit($example['target_unit'], 1, 1)],
        );

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);

        $skillLogs = array_values(array_filter(
            $result['data']['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'skill_cast'
        ));

        $this->assertSame(
            array_map(
                static fn (array $expected): array => [
                    'tick' => 1,
                    'actor' => $example['attacker_unit']['unit_id'],
                    'target' => $example['target_unit']['unit_id'],
                    'action' => 'skill_cast',
                    'skill_id' => $example['skill_id'],
                    'raw_damage' => $expected['raw_damage'],
                    'is_critical' => $expected['is_critical'],
                    'shield_absorbed' => $expected['shield_absorbed'],
                    'hp_damage' => $expected['hp_damage'],
                    'hit_index' => $expected['hit_index'],
                ],
                $example['expected_logs']
            ),
            $skillLogs
        );
    }

    public function test_aoe_skill_logs_each_target_separately(): void
    {
        $example = $this->loadExamples()['aoe_example'];

        $runtimeState = $this->buildRuntimeState(
            $this->buildPlayerUnit($example['attacker_unit'], [$example['skill_state']]),
            [
                $this->buildEnemyUnit($example['enemy_wave_units'][0], 1, 1),
                $this->buildEnemyUnit($example['enemy_wave_units'][1], 1, 2),
            ],
        );

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);

        $skillLogs = array_values(array_filter(
            $result['data']['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'skill_cast'
        ));

        $this->assertSame(
            array_map(
                static fn (array $expected): array => [
                    'tick' => 1,
                    'actor' => $example['attacker_unit']['unit_id'],
                    'target' => $expected['target'],
                    'action' => 'skill_cast',
                    'skill_id' => $example['skill_id'],
                    'raw_damage' => $expected['raw_damage'],
                    'is_critical' => $expected['is_critical'],
                    'shield_absorbed' => $expected['shield_absorbed'],
                    'hp_damage' => $expected['hp_damage'],
                ],
                $example['expected_logs']
            ),
            $skillLogs
        );
    }

    public function test_self_buff_skill_writes_effect_apply_log_and_runtime_modifier(): void
    {
        $example = $this->loadExamples()['self_buff_example'];

        $playerUnit = $this->buildPlayerUnit($example['attacker_unit'], [$example['skill_state']]);
        $playerUnit['runtime_modifiers'] = $example['runtime_modifiers_before'];

        $runtimeState = $this->buildRuntimeState($playerUnit, []);

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_runtime_modifiers_after'], $result['data']['player_unit']['runtime_modifiers']);

        $effectLogs = array_values(array_filter(
            $result['data']['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'effect_apply'
        ));

        $this->assertSame([
            'tick' => 1,
            'actor' => $example['attacker_unit']['unit_id'],
            'action' => 'effect_apply',
            'effect_key' => 'bonus_atk_percent',
            'value' => 20,
            'source' => $example['skill_id'],
        ], $effectLogs[0]);
    }

    public function test_shield_skill_writes_effect_apply_log_and_shield(): void
    {
        $example = $this->loadExamples()['shield_example'];

        $playerUnit = $this->buildPlayerUnit($example['attacker_unit'], [$example['skill_state']]);
        $playerUnit['shield'] = $example['shield_before'];

        $runtimeState = $this->buildRuntimeState($playerUnit, []);

        $result = app(CombatTickRunner::class)->runTick($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_shield_after'], $result['data']['player_unit']['shield']);

        $effectLogs = array_values(array_filter(
            $result['data']['logs'],
            static fn (array $log): bool => ($log['action'] ?? '') === 'effect_apply'
        ));

        $this->assertSame([
            'tick' => 1,
            'actor' => $example['attacker_unit']['unit_id'],
            'action' => 'effect_apply',
            'effect_key' => 'shield',
            'value' => 150,
            'source' => $example['skill_id'],
        ], $effectLogs[0]);
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @return array<string, mixed>
     */
    private function buildRuntimeState(array $playerUnit, array $enemyUnits): array
    {
        return [
            'battle_id' => 'battle_runtime_multi_skill_types',
            'status' => 'running',
            'tick' => 0,
            'current_wave_index' => 1,
            'player_unit' => $playerUnit,
            'enemy_units' => $enemyUnits,
            'battle_context' => [],
            'logs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $skills
     * @return array<string, mixed>
     */
    private function buildPlayerUnit(array $payload, array $skills): array
    {
        $stats = [];
        foreach (['MELEE_ATK', 'ATK', 'DEF', 'HP'] as $statKey) {
            if (array_key_exists($statKey, $payload)) {
                $stats[$statKey] = $payload[$statKey];
            }
        }

        $currentHp = max(0, (int) ($payload['current_hp'] ?? $payload['HP'] ?? 850));

        return [
            'unit_id' => $payload['unit_id'],
            'side' => 'player',
            'current_hp' => $currentHp,
            'max_hp' => $currentHp,
            'stats' => $stats,
            'bonus_stats' => [],
            'skills' => $skills,
            'runtime_effects' => [],
            'runtime_modifiers' => [],
            'runtime_tags' => [],
            'shield' => max(0, (int) ($payload['shield'] ?? 0)),
            'alive' => $currentHp > 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildEnemyUnit(array $payload, int $waveIndex, int $unitIndex): array
    {
        $stats = [];
        foreach (['MELEE_ATK', 'ATK', 'DEF', 'HP'] as $statKey) {
            if (array_key_exists($statKey, $payload)) {
                $stats[$statKey] = $payload[$statKey];
            }
        }

        $currentHp = max(0, (int) ($payload['current_hp'] ?? $payload['HP'] ?? 100));

        return [
            'unit_id' => $payload['unit_id'],
            'side' => 'enemy',
            'wave_index' => $waveIndex,
            'unit_index' => $unitIndex,
            'current_hp' => $currentHp,
            'max_hp' => $currentHp,
            'stats' => $stats,
            'skills' => [],
            'runtime_effects' => [],
            'runtime_modifiers' => [],
            'runtime_tags' => [],
            'shield' => max(0, (int) ($payload['shield'] ?? 0)),
            'alive' => $currentHp > 0,
        ];
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/multi_skill_types_examples_v1.json')), true);
    }
}
