<?php

namespace App\Services\Game\Battle;

use App\Models\MainStageDifficulty;
use App\Models\Monster;
use App\Models\StageDifficultyMonster;
use Illuminate\Database\Eloquent\Collection;

class EnemyBattleSnapshotBuilder
{
    private const WAVE_INDEX_BY_SPAWN_TYPE = [
        'normal' => 1,
        'elite' => 2,
        'boss' => 3,
    ];

    public function build(array $stageContext, ?array $monsterConfigs = null): array
    {
        $configs = $monsterConfigs ?? $this->loadMonsterConfigs($stageContext);
        if ($configs === null) {
            return $this->failure('monster_configs_not_found');
        }

        $snapshots = [];
        $unitIndexes = [];

        foreach ($configs as $config) {
            $monster = $this->extractMonster($config);
            if (! $monster instanceof Monster) {
                return $this->failure('monster_not_found');
            }

            $spawnType = $this->resolveSpawnType($config, $monster);
            $waveIndex = self::WAVE_INDEX_BY_SPAWN_TYPE[$spawnType] ?? 1;
            $count = $this->resolveUnitCount($config);

            for ($i = 0; $i < $count; $i++) {
                $unitIndexes[$waveIndex] = ($unitIndexes[$waveIndex] ?? 0) + 1;

                $snapshots[] = [
                    'monster_id' => (string) $monster->monster_id,
                    'wave_index' => $waveIndex,
                    'unit_index' => $unitIndexes[$waveIndex],
                    'is_boss' => $spawnType === 'boss' || (string) $monster->monster_type === 'boss',
                    'base_stats' => [
                        'HP' => (int) $monster->hp,
                        'ATK' => (int) $monster->atk,
                        'DEF' => (int) $monster->def,
                    ],
                    'skills' => $monster->skillBindings
                        ->filter(static fn ($binding): bool => (bool) $binding->is_enabled)
                        ->pluck('skill_id')
                        ->map(static fn (mixed $skillId): string => trim((string) $skillId))
                        ->filter()
                        ->values()
                        ->all(),
                    'tags' => $this->buildTags($monster),
                ];
            }
        }

        usort($snapshots, static function (array $left, array $right): int {
            return [$left['wave_index'], $left['unit_index'], $left['monster_id']]
                <=> [$right['wave_index'], $right['unit_index'], $right['monster_id']];
        });

        return $this->success($snapshots);
    }

    /**
     * @return array<int, StageDifficultyMonster>|null
     */
    private function loadMonsterConfigs(array $stageContext): ?array
    {
        $difficultyId = trim((string) ($stageContext['difficulty_id'] ?? ''));
        if ($difficultyId === '') {
            return null;
        }

        $difficulty = MainStageDifficulty::query()
            ->with([
                'monsterEntries' => fn ($query) => $query
                    ->where('is_enabled', true)
                    ->with([
                        'monster' => fn ($monsterQuery) => $monsterQuery
                            ->with(['skillBindings' => fn ($skillQuery) => $skillQuery->orderBy('sort_order')->orderBy('id')])
                            ->where('is_enabled', true),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->where('difficulty_id', $difficultyId)
            ->where('is_enabled', true)
            ->first();

        if (! $difficulty instanceof MainStageDifficulty) {
            return null;
        }

        return $difficulty->monsterEntries->all();
    }

    private function extractMonster(mixed $config): ?Monster
    {
        if ($config instanceof StageDifficultyMonster) {
            return $config->monster;
        }

        if ($config instanceof Monster) {
            return $config;
        }

        if (is_array($config) && ($config['monster'] ?? null) instanceof Monster) {
            return $config['monster'];
        }

        if (is_array($config) && is_array($config['monster'] ?? null)) {
            $monster = new Monster();
            $monster->forceFill($config['monster']);

            $skillBindings = new Collection();
            foreach (is_array($config['monster']['skill_bindings'] ?? null) ? $config['monster']['skill_bindings'] : [] as $binding) {
                $skillBinding = new \App\Models\MonsterSkillBinding();
                $skillBinding->forceFill(is_array($binding) ? $binding : []);
                $skillBindings->push($skillBinding);
            }
            $monster->setRelation('skillBindings', $skillBindings);

            return $monster;
        }

        return null;
    }

    private function resolveSpawnType(mixed $config, Monster $monster): string
    {
        $spawnType = '';

        if ($config instanceof StageDifficultyMonster) {
            $spawnType = (string) $config->spawn_type;
        } elseif (is_array($config)) {
            $spawnType = trim((string) ($config['spawn_type'] ?? ''));
        }

        if ($spawnType !== '') {
            return $spawnType;
        }

        return trim((string) $monster->monster_type) !== '' ? (string) $monster->monster_type : 'normal';
    }

    private function resolveUnitCount(mixed $config): int
    {
        $minCount = 0;

        if ($config instanceof StageDifficultyMonster) {
            $minCount = (int) $config->min_count;
        } elseif (is_array($config)) {
            $minCount = (int) ($config['min_count'] ?? 0);
        }

        return max(1, $minCount);
    }

    /**
     * @return array<int, string>
     */
    private function buildTags(Monster $monster): array
    {
        $tags = [];

        foreach ([
            trim((string) $monster->monster_type),
            trim((string) ($monster->family ?? '')),
            trim((string) ($monster->rarity_tag ?? '')),
        ] as $tag) {
            if ($tag === '') {
                continue;
            }

            $tags[$tag] = $tag;
        }

        return array_values($tags);
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    private function failure(string $reason): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => null,
        ];
    }
}
