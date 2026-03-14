<?php

namespace App\Services\Game\Battle;

class BattleResultBuilder
{
    public function __construct(
        private readonly BattleVictoryResolver $battleVictoryResolver = new BattleVictoryResolver(),
    ) {
    }

    public function build(array $runtimeState): array
    {
        $battleResultResponse = $this->battleVictoryResolver->resolve($runtimeState);
        if (! ($battleResultResponse['ok'] ?? false)) {
            return [
                'ok' => false,
                'reason' => (string) ($battleResultResponse['reason'] ?? 'battle_result_resolve_failed'),
                'data' => null,
            ];
        }

        $enemyUnits = is_array($runtimeState['enemy_units'] ?? null) ? $runtimeState['enemy_units'] : [];
        $remainingEnemyCount = 0;

        foreach ($enemyUnits as $enemyUnit) {
            if (($enemyUnit['alive'] ?? false) === true) {
                $remainingEnemyCount++;
            }
        }

        return $this->success([
            'battle_result' => (string) ($battleResultResponse['data']['battle_result'] ?? 'ongoing'),
            'elapsed_ticks' => max(0, (int) ($runtimeState['tick'] ?? 0)),
            'remaining_player_hp' => max(0, (int) ($runtimeState['player_unit']['current_hp'] ?? 0)),
            'remaining_enemy_count' => $remainingEnemyCount,
            'cleared_wave_count' => $this->countClearedWaves($enemyUnits),
            'logs' => is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function countClearedWaves(array $enemyUnits): int
    {
        $waves = [];

        foreach ($enemyUnits as $enemyUnit) {
            $waveIndex = (int) ($enemyUnit['wave_index'] ?? 0);
            if ($waveIndex <= 0) {
                continue;
            }

            $waves[$waveIndex][] = (bool) ($enemyUnit['alive'] ?? false);
        }

        $clearedWaveCount = 0;
        foreach ($waves as $waveStates) {
            if (! in_array(true, $waveStates, true)) {
                $clearedWaveCount++;
            }
        }

        return $clearedWaveCount;
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }
}
