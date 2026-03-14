<?php

namespace App\Services\Game\Battle;

class CombatTickRunner
{
    public function __construct(
        private readonly BasicDamageResolver $basicDamageResolver = new BasicDamageResolver(),
        private readonly BattleVictoryResolver $battleVictoryResolver = new BattleVictoryResolver(),
    ) {
    }

    public function runTick(array $runtimeState): array
    {
        if (($runtimeState['status'] ?? 'running') !== 'running') {
            return $this->success($runtimeState);
        }

        $runtimeState['tick'] = max(0, (int) ($runtimeState['tick'] ?? 0)) + 1;
        $runtimeState['logs'] = is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [];
        $tick = (int) $runtimeState['tick'];

        $currentWaveIndex = max(1, (int) ($runtimeState['current_wave_index'] ?? 1));
        $playerUnit = is_array($runtimeState['player_unit'] ?? null) ? $runtimeState['player_unit'] : [];
        $enemyUnits = is_array($runtimeState['enemy_units'] ?? null) ? $runtimeState['enemy_units'] : [];

        $playerTargetIndex = $this->findFirstAliveEnemyIndex($enemyUnits, $currentWaveIndex);
        if ($playerTargetIndex !== null && ($playerUnit['alive'] ?? false) === true) {
            $damageResult = $this->basicDamageResolver->resolvePlayerToEnemy($playerUnit, $enemyUnits[$playerTargetIndex]);
            if (! ($damageResult['ok'] ?? false)) {
                return $this->failure((string) ($damageResult['reason'] ?? 'player_attack_resolve_failed'));
            }

            $damage = max(1, (int) ($damageResult['data']['damage'] ?? 1));
            $enemyUnits[$playerTargetIndex]['current_hp'] = max(0, (int) $enemyUnits[$playerTargetIndex]['current_hp'] - $damage);
            $enemyUnits[$playerTargetIndex]['alive'] = $enemyUnits[$playerTargetIndex]['current_hp'] > 0;

            $runtimeState['logs'][] = $this->buildAttackLog(
                $tick,
                (string) ($playerUnit['unit_id'] ?? 'player'),
                (string) ($enemyUnits[$playerTargetIndex]['unit_id'] ?? 'enemy'),
                $damage
            );

            if (($enemyUnits[$playerTargetIndex]['alive'] ?? false) !== true) {
                $runtimeState['logs'][] = $this->buildStateLog(
                    $tick,
                    'unit_dead',
                    (string) ($enemyUnits[$playerTargetIndex]['unit_id'] ?? 'enemy')
                );
            }
        }

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            if ((int) ($enemyUnit['wave_index'] ?? 0) !== $currentWaveIndex || ($enemyUnit['alive'] ?? false) !== true) {
                continue;
            }

            if (($playerUnit['alive'] ?? false) !== true) {
                break;
            }

            $damageResult = $this->basicDamageResolver->resolveEnemyToPlayer($enemyUnit, $playerUnit);
            if (! ($damageResult['ok'] ?? false)) {
                return $this->failure((string) ($damageResult['reason'] ?? 'enemy_attack_resolve_failed'));
            }

            $damage = max(1, (int) ($damageResult['data']['damage'] ?? 1));
            $playerUnit['current_hp'] = max(0, (int) $playerUnit['current_hp'] - $damage);
            $playerUnit['alive'] = $playerUnit['current_hp'] > 0;

            $runtimeState['logs'][] = $this->buildAttackLog(
                $tick,
                (string) ($enemyUnit['unit_id'] ?? 'enemy'),
                (string) ($playerUnit['unit_id'] ?? 'player'),
                $damage
            );

            if (($playerUnit['alive'] ?? false) !== true) {
                $runtimeState['logs'][] = $this->buildStateLog(
                    $tick,
                    'unit_dead',
                    (string) ($playerUnit['unit_id'] ?? 'player')
                );
                break;
            }

            $enemyUnits[$enemyIndex] = $enemyUnit;
        }

        $runtimeState['player_unit'] = $playerUnit;
        $runtimeState['enemy_units'] = $enemyUnits;

        $nextWaveIndex = $this->resolveNextWaveIndex($enemyUnits, $currentWaveIndex);
        if ($nextWaveIndex !== $currentWaveIndex) {
            $runtimeState['current_wave_index'] = $nextWaveIndex;
            $runtimeState['logs'][] = [
                'tick' => $tick,
                'actor' => null,
                'target' => null,
                'action' => 'wave_switch',
                'damage' => null,
                'wave_index' => $nextWaveIndex,
            ];
        }

        $battleResultResponse = $this->battleVictoryResolver->resolve($runtimeState);
        if (! ($battleResultResponse['ok'] ?? false)) {
            return $this->failure((string) ($battleResultResponse['reason'] ?? 'battle_victory_resolve_failed'));
        }

        $battleResult = (string) ($battleResultResponse['data']['battle_result'] ?? 'ongoing');
        if ($battleResult !== 'ongoing') {
            $runtimeState['status'] = 'finished';
            $runtimeState['logs'][] = [
                'tick' => $tick,
                'actor' => null,
                'target' => null,
                'action' => 'battle_end',
                'damage' => null,
                'battle_result' => $battleResult,
            ];
        }

        return $this->success($runtimeState);
    }

    /**
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function findFirstAliveEnemyIndex(array $enemyUnits, int $waveIndex): ?int
    {
        $selectedIndex = null;
        $selectedKey = null;

        foreach ($enemyUnits as $index => $enemyUnit) {
            if ((int) ($enemyUnit['wave_index'] ?? 0) !== $waveIndex || ($enemyUnit['alive'] ?? false) !== true) {
                continue;
            }

            $candidateKey = [(int) ($enemyUnit['unit_index'] ?? PHP_INT_MAX), (string) ($enemyUnit['unit_id'] ?? '')];
            if ($selectedKey === null || $candidateKey < $selectedKey) {
                $selectedKey = $candidateKey;
                $selectedIndex = $index;
            }
        }

        return $selectedIndex;
    }

    /**
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function resolveNextWaveIndex(array $enemyUnits, int $currentWaveIndex): int
    {
        $currentWaveHasAliveEnemies = false;
        $aliveWaveIndexes = [];

        foreach ($enemyUnits as $enemyUnit) {
            if (($enemyUnit['alive'] ?? false) !== true) {
                continue;
            }

            $waveIndex = max(1, (int) ($enemyUnit['wave_index'] ?? 1));
            $aliveWaveIndexes[$waveIndex] = $waveIndex;

            if ($waveIndex === $currentWaveIndex) {
                $currentWaveHasAliveEnemies = true;
            }
        }

        if ($currentWaveHasAliveEnemies || $aliveWaveIndexes === []) {
            return $currentWaveIndex;
        }

        sort($aliveWaveIndexes);

        foreach ($aliveWaveIndexes as $waveIndex) {
            if ($waveIndex > $currentWaveIndex) {
                return $waveIndex;
            }
        }

        return (int) reset($aliveWaveIndexes);
    }

    private function buildAttackLog(int $tick, string $actor, string $target, int $damage): array
    {
        return [
            'tick' => $tick,
            'actor' => $actor,
            'target' => $target,
            'action' => 'basic_attack',
            'damage' => $damage,
        ];
    }

    private function buildStateLog(int $tick, string $action, string $target): array
    {
        return [
            'tick' => $tick,
            'actor' => null,
            'target' => $target,
            'action' => $action,
            'damage' => null,
        ];
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
