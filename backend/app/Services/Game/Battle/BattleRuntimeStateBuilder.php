<?php

namespace App\Services\Game\Battle;

use Illuminate\Support\Str;

class BattleRuntimeStateBuilder
{
    public function build(array $payload): array
    {
        $playerSnapshot = is_array($payload['player_snapshot'] ?? null) ? $payload['player_snapshot'] : null;
        $enemySnapshots = is_array($payload['enemy_snapshots'] ?? null) ? $payload['enemy_snapshots'] : null;
        $battleContext = is_array($payload['battle_context'] ?? null) ? $payload['battle_context'] : null;

        if ($playerSnapshot === null || $enemySnapshots === null || $battleContext === null) {
            return $this->failure('invalid_battle_start_payload');
        }

        $playerUnit = $this->buildPlayerUnit($playerSnapshot);
        if ($playerUnit === null) {
            return $this->failure('invalid_player_snapshot');
        }

        $enemyUnits = [];
        foreach ($enemySnapshots as $enemySnapshot) {
            if (! is_array($enemySnapshot)) {
                return $this->failure('invalid_enemy_snapshot');
            }

            $enemyUnit = $this->buildEnemyUnit($enemySnapshot);
            if ($enemyUnit === null) {
                return $this->failure('invalid_enemy_snapshot');
            }

            $enemyUnits[] = $enemyUnit;
        }

        usort($enemyUnits, static fn (array $left, array $right): int => [$left['wave_index'], $left['unit_index'], $left['unit_id']]
            <=> [$right['wave_index'], $right['unit_index'], $right['unit_id']]);

        return $this->success([
            'battle_id' => $this->resolveBattleId($payload, $playerSnapshot),
            'status' => 'running',
            'tick' => 0,
            'current_wave_index' => $this->resolveCurrentWaveIndex($enemyUnits),
            'player_unit' => $playerUnit,
            'enemy_units' => $enemyUnits,
            'battle_context' => $battleContext,
            'logs' => [],
        ]);
    }

    private function buildPlayerUnit(array $playerSnapshot): ?array
    {
        $playerId = $playerSnapshot['player_id'] ?? null;
        $stats = is_array($playerSnapshot['base_stats'] ?? null) ? $playerSnapshot['base_stats'] : null;

        if ($playerId === null || $stats === null) {
            return null;
        }

        $maxHp = max(0, (int) ($stats['HP'] ?? 0));

        return [
            'unit_id' => 'player_'.trim((string) $playerId),
            'side' => 'player',
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'stats' => $stats,
            'bonus_stats' => is_array($playerSnapshot['bonus_stats'] ?? null) ? $playerSnapshot['bonus_stats'] : [],
            'special_effects' => is_array($playerSnapshot['special_effects'] ?? null) ? array_values($playerSnapshot['special_effects']) : [],
            'alive' => $maxHp > 0,
        ];
    }

    private function buildEnemyUnit(array $enemySnapshot): ?array
    {
        $monsterId = trim((string) ($enemySnapshot['monster_id'] ?? ''));
        $waveIndex = (int) ($enemySnapshot['wave_index'] ?? 0);
        $unitIndex = (int) ($enemySnapshot['unit_index'] ?? 0);
        $stats = is_array($enemySnapshot['base_stats'] ?? null) ? $enemySnapshot['base_stats'] : null;

        if ($monsterId === '' || $waveIndex <= 0 || $unitIndex <= 0 || $stats === null) {
            return null;
        }

        $maxHp = max(0, (int) ($stats['HP'] ?? 0));

        return [
            'unit_id' => sprintf('enemy_%s_%d_%d', $monsterId, $waveIndex, $unitIndex),
            'monster_id' => $monsterId,
            'side' => 'enemy',
            'wave_index' => $waveIndex,
            'unit_index' => $unitIndex,
            'is_boss' => (bool) ($enemySnapshot['is_boss'] ?? false),
            'current_hp' => $maxHp,
            'max_hp' => $maxHp,
            'stats' => $stats,
            'skills' => is_array($enemySnapshot['skills'] ?? null) ? array_values($enemySnapshot['skills']) : [],
            'tags' => is_array($enemySnapshot['tags'] ?? null) ? array_values($enemySnapshot['tags']) : [],
            'alive' => $maxHp > 0,
        ];
    }

    private function resolveBattleId(array $payload, array $playerSnapshot): string
    {
        $battleId = trim((string) ($payload['battle_id'] ?? ''));
        if ($battleId !== '') {
            return $battleId;
        }

        $playerId = trim((string) ($playerSnapshot['player_id'] ?? 'player'));

        return 'battle_runtime_'.$playerId.'_'.Str::lower(Str::random(8));
    }

    /**
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function resolveCurrentWaveIndex(array $enemyUnits): int
    {
        foreach ($enemyUnits as $enemyUnit) {
            if (($enemyUnit['alive'] ?? false) !== true) {
                continue;
            }

            return max(1, (int) ($enemyUnit['wave_index'] ?? 1));
        }

        return 1;
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
