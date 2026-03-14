<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\MultiSkillTypeResolver;
use Tests\TestCase;

class MultiSkillTypeResolverTest extends TestCase
{
    public function test_multi_hit_skill_resolves_all_hits_from_example(): void
    {
        $example = $this->loadExamples()['multi_hit_example'];

        $result = app(MultiSkillTypeResolver::class)->resolve(
            $this->buildUnit($example['attacker_unit'], 'player'),
            [$this->buildEnemyUnit($example['target_unit'], 1, 1)],
            $example['skill_state'],
            ['primary_target_index' => 0]
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(
            $example['expected_logs'],
            array_map(
                static fn (array $entry): array => [
                    'hit_index' => $entry['hit_index'],
                    'raw_damage' => $entry['raw_damage'],
                    'hp_damage' => $entry['hp_damage'],
                    'shield_absorbed' => $entry['shield_absorbed'],
                    'is_critical' => $entry['is_critical'],
                ],
                $result['data']['entries']
            )
        );
        $this->assertSame(0, $result['data']['target_units'][0]['current_hp']);
    }

    public function test_aoe_skill_resolves_all_wave_targets_from_example(): void
    {
        $example = $this->loadExamples()['aoe_example'];

        $result = app(MultiSkillTypeResolver::class)->resolve(
            $this->buildUnit($example['attacker_unit'], 'player'),
            [
                $this->buildEnemyUnit($example['enemy_wave_units'][0], 1, 1),
                $this->buildEnemyUnit($example['enemy_wave_units'][1], 1, 2),
            ],
            $example['skill_state'],
            ['target_indexes' => [0, 1]]
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(
            $example['expected_logs'],
            array_map(
                static fn (array $entry): array => [
                    'target' => $entry['target_unit_id'],
                    'raw_damage' => $entry['raw_damage'],
                    'hp_damage' => $entry['hp_damage'],
                    'shield_absorbed' => $entry['shield_absorbed'],
                    'is_critical' => $entry['is_critical'],
                ],
                $result['data']['entries']
            )
        );
        $this->assertSame(0, $result['data']['target_units'][0]['current_hp']);
        $this->assertSame(0, $result['data']['target_units'][1]['current_hp']);
    }

    public function test_self_buff_skill_writes_runtime_modifier_from_example(): void
    {
        $example = $this->loadExamples()['self_buff_example'];

        $actorUnit = $this->buildUnit($example['attacker_unit'], 'player');
        $actorUnit['runtime_modifiers'] = $example['runtime_modifiers_before'];

        $result = app(MultiSkillTypeResolver::class)->resolve(
            $actorUnit,
            [],
            $example['skill_state'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_runtime_modifiers_after'], $result['data']['actor_unit']['runtime_modifiers']);
        $this->assertSame($example['expected_result']['raw_damage'], $result['data']['entries'][0]['raw_damage']);
        $this->assertSame($example['expected_result']['hp_damage'], $result['data']['entries'][0]['hp_damage']);
        $this->assertSame($example['expected_result']['shield_absorbed'], $result['data']['entries'][0]['shield_absorbed']);
        $this->assertSame('bonus_atk_percent', $result['data']['entries'][0]['effect_key']);
    }

    public function test_shield_skill_writes_shield_from_example(): void
    {
        $example = $this->loadExamples()['shield_example'];

        $actorUnit = $this->buildUnit($example['attacker_unit'], 'player');
        $actorUnit['shield'] = $example['shield_before'];

        $result = app(MultiSkillTypeResolver::class)->resolve(
            $actorUnit,
            [],
            $example['skill_state'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($example['expected_shield_after'], $result['data']['actor_unit']['shield']);
        $this->assertSame($example['expected_result']['raw_damage'], $result['data']['entries'][0]['raw_damage']);
        $this->assertSame($example['expected_result']['hp_damage'], $result['data']['entries'][0]['hp_damage']);
        $this->assertSame($example['expected_result']['shield_absorbed'], $result['data']['entries'][0]['shield_absorbed']);
        $this->assertSame('shield', $result['data']['entries'][0]['effect_key']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildUnit(array $payload, string $side): array
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
            'side' => $side,
            'current_hp' => $currentHp,
            'max_hp' => $currentHp,
            'stats' => $stats,
            'bonus_stats' => is_array($payload['bonus_stats'] ?? null) ? $payload['bonus_stats'] : [],
            'runtime_modifiers' => [],
            'runtime_effects' => [],
            'runtime_tags' => [],
            'shield' => max(0, (int) ($payload['shield'] ?? 0)),
            'alive' => $currentHp > 0,
        ] + $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildEnemyUnit(array $payload, int $waveIndex, int $unitIndex): array
    {
        return $this->buildUnit($payload, 'enemy') + [
            'wave_index' => $waveIndex,
            'unit_index' => $unitIndex,
        ];
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/multi_skill_types_examples_v1.json')), true);
    }
}
