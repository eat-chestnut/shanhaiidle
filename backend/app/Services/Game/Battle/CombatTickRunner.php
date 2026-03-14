<?php

namespace App\Services\Game\Battle;

class CombatTickRunner
{
    public function __construct(
        private readonly BasicDamageResolver $basicDamageResolver = new BasicDamageResolver(),
        private readonly BattleVictoryResolver $battleVictoryResolver = new BattleVictoryResolver(),
        private readonly SkillRuntimeStateBuilder $skillRuntimeStateBuilder = new SkillRuntimeStateBuilder(),
        private readonly SkillCooldownResolver $skillCooldownResolver = new SkillCooldownResolver(),
        private readonly SkillCastResolver $skillCastResolver = new SkillCastResolver(),
        private readonly SkillDamageResolver $skillDamageResolver = new SkillDamageResolver(),
        private readonly SkillActionLogger $skillActionLogger = new SkillActionLogger(),
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

        $playerSkillStateResult = $this->ensureUnitSkillStates($runtimeState, $playerUnit);
        if (! ($playerSkillStateResult['ok'] ?? false)) {
            return $this->failure((string) ($playerSkillStateResult['reason'] ?? 'player_skill_state_build_failed'));
        }
        $playerSkillStates = $playerSkillStateResult['data']['skills'];
        $playerUnit['skill_states'] = $playerSkillStates;

        if (($playerUnit['alive'] ?? false) === true && max(0, (int) ($playerUnit['current_hp'] ?? 0)) > 0) {
            $playerCastResult = $this->skillCastResolver->resolvePlayerCast($playerUnit, $enemyUnits, $playerSkillStates);
            if (! ($playerCastResult['ok'] ?? false)) {
                return $this->failure((string) ($playerCastResult['reason'] ?? 'player_skill_cast_resolve_failed'));
            }

            $playerDidCastSkill = false;
            if (($playerCastResult['data']['can_cast'] ?? false) === true) {
                $playerDidCastSkill = $this->applyPlayerSkillCast(
                    $runtimeState,
                    $playerUnit,
                    $enemyUnits,
                    $playerSkillStates,
                    $playerCastResult['data'],
                    $tick,
                );

                if ($playerDidCastSkill === false) {
                    return $this->failure('player_skill_cast_apply_failed');
                }
            }

            if (! $playerDidCastSkill) {
                $playerTargetIndex = $this->findFirstAliveEnemyIndex($enemyUnits, $currentWaveIndex);
                if ($playerTargetIndex !== null) {
                    $damageResult = $this->basicDamageResolver->resolvePlayerToEnemy($playerUnit, $enemyUnits[$playerTargetIndex]);
                    if (! ($damageResult['ok'] ?? false)) {
                        return $this->failure((string) ($damageResult['reason'] ?? 'player_attack_resolve_failed'));
                    }

                    $damage = max(1, (int) ($damageResult['data']['damage'] ?? 1));
                    $enemyUnits[$playerTargetIndex]['current_hp'] = max(0, (int) $enemyUnits[$playerTargetIndex]['current_hp'] - $damage);

                    $runtimeState['logs'][] = $this->buildAttackLog(
                        $tick,
                        (string) ($playerUnit['unit_id'] ?? 'player'),
                        (string) ($enemyUnits[$playerTargetIndex]['unit_id'] ?? 'enemy'),
                        $damage
                    );
                }
            }
        }

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            if (
                (int) ($enemyUnit['wave_index'] ?? 0) !== $currentWaveIndex
                || ($enemyUnit['alive'] ?? false) !== true
                || max(0, (int) ($enemyUnit['current_hp'] ?? 0)) <= 0
            ) {
                continue;
            }

            if (($playerUnit['alive'] ?? false) !== true || max(0, (int) ($playerUnit['current_hp'] ?? 0)) <= 0) {
                break;
            }

            $enemySkillStateResult = $this->ensureUnitSkillStates($runtimeState, $enemyUnit);
            if (! ($enemySkillStateResult['ok'] ?? false)) {
                return $this->failure((string) ($enemySkillStateResult['reason'] ?? 'enemy_skill_state_build_failed'));
            }
            $enemySkillStates = $enemySkillStateResult['data']['skills'];
            $enemyUnit['skill_states'] = $enemySkillStates;

            $enemyCastResult = $this->skillCastResolver->resolveEnemyCast($enemyUnit, $playerUnit, $enemySkillStates);
            if (! ($enemyCastResult['ok'] ?? false)) {
                return $this->failure((string) ($enemyCastResult['reason'] ?? 'enemy_skill_cast_resolve_failed'));
            }

            $enemyDidCastSkill = false;
            if (($enemyCastResult['data']['can_cast'] ?? false) === true) {
                $enemyDidCastSkill = $this->applyEnemySkillCast(
                    $runtimeState,
                    $enemyUnit,
                    $playerUnit,
                    $enemySkillStates,
                    $enemyCastResult['data'],
                    $tick,
                );

                if ($enemyDidCastSkill === false) {
                    return $this->failure('enemy_skill_cast_apply_failed');
                }
            }

            if (! $enemyDidCastSkill) {
                $damageResult = $this->basicDamageResolver->resolveEnemyToPlayer($enemyUnit, $playerUnit);
                if (! ($damageResult['ok'] ?? false)) {
                    return $this->failure((string) ($damageResult['reason'] ?? 'enemy_attack_resolve_failed'));
                }

                $damage = max(1, (int) ($damageResult['data']['damage'] ?? 1));
                $playerUnit['current_hp'] = max(0, (int) $playerUnit['current_hp'] - $damage);

                $runtimeState['logs'][] = $this->buildAttackLog(
                    $tick,
                    (string) ($enemyUnit['unit_id'] ?? 'enemy'),
                    (string) ($playerUnit['unit_id'] ?? 'player'),
                    $damage
                );
            }

            $enemyUnit['skill_states'] = $enemySkillStates;
            $enemyUnits[$enemyIndex] = $enemyUnit;

            if (max(0, (int) ($playerUnit['current_hp'] ?? 0)) <= 0) {
                break;
            }
        }

        $deathSettlementResult = $this->settleDeaths($runtimeState, $tick, $playerUnit, $enemyUnits);
        if (! ($deathSettlementResult['ok'] ?? false)) {
            return $this->failure((string) ($deathSettlementResult['reason'] ?? 'death_settlement_failed'));
        }
        $runtimeState = $deathSettlementResult['data']['runtime_state'];
        $playerUnit = $deathSettlementResult['data']['player_unit'];
        $enemyUnits = $deathSettlementResult['data']['enemy_units'];

        $cooldownStepResult = $this->tickSkillCooldowns($playerUnit, $enemyUnits);
        if (! ($cooldownStepResult['ok'] ?? false)) {
            return $this->failure((string) ($cooldownStepResult['reason'] ?? 'skill_cooldown_tick_failed'));
        }
        $playerUnit = $cooldownStepResult['data']['player_unit'];
        $enemyUnits = $cooldownStepResult['data']['enemy_units'];

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
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @param  array<int, array<string, mixed>>  $playerSkillStates
     * @param  array<string, mixed>  $castData
     */
    private function applyPlayerSkillCast(
        array &$runtimeState,
        array &$playerUnit,
        array &$enemyUnits,
        array &$playerSkillStates,
        array $castData,
        int $tick,
    ): bool {
        $targetEnemyIndex = $castData['target_enemy_index'] ?? null;
        $skillState = is_array($castData['skill'] ?? null) ? $castData['skill'] : null;

        if (! is_int($targetEnemyIndex) || ! isset($enemyUnits[$targetEnemyIndex]) || ! is_array($skillState)) {
            return false;
        }

        $damageResult = $this->skillDamageResolver->resolvePlayerSkillDamage($playerUnit, $enemyUnits[$targetEnemyIndex], $skillState);
        if (! ($damageResult['ok'] ?? false)) {
            return false;
        }

        $damage = max(1, (int) ($damageResult['data']['damage'] ?? 1));
        $enemyUnits[$targetEnemyIndex]['current_hp'] = max(0, (int) $enemyUnits[$targetEnemyIndex]['current_hp'] - $damage);

        $logResult = $this->skillActionLogger->logSkillCast(
            $runtimeState,
            $tick,
            (string) ($playerUnit['unit_id'] ?? 'player'),
            (string) ($enemyUnits[$targetEnemyIndex]['unit_id'] ?? 'enemy'),
            (string) ($skillState['skill_id'] ?? ''),
            $damage,
        );

        if (! ($logResult['ok'] ?? false)) {
            return false;
        }

        $runtimeState = $logResult['data']['runtime_state'];
        $playerSkillStates = $this->markSkillAsCast($playerSkillStates, $skillState);
        $playerUnit['skill_states'] = $playerSkillStates;

        return true;
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, mixed>  $enemyUnit
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemySkillStates
     * @param  array<string, mixed>  $castData
     */
    private function applyEnemySkillCast(
        array &$runtimeState,
        array &$enemyUnit,
        array &$playerUnit,
        array &$enemySkillStates,
        array $castData,
        int $tick,
    ): bool {
        $skillState = is_array($castData['skill'] ?? null) ? $castData['skill'] : null;
        if (! is_array($skillState)) {
            return false;
        }

        $damageResult = $this->skillDamageResolver->resolveEnemySkillDamage($enemyUnit, $playerUnit, $skillState);
        if (! ($damageResult['ok'] ?? false)) {
            return false;
        }

        $damage = max(1, (int) ($damageResult['data']['damage'] ?? 1));
        $playerUnit['current_hp'] = max(0, (int) $playerUnit['current_hp'] - $damage);

        $logResult = $this->skillActionLogger->logSkillCast(
            $runtimeState,
            $tick,
            (string) ($enemyUnit['unit_id'] ?? 'enemy'),
            (string) ($playerUnit['unit_id'] ?? 'player'),
            (string) ($skillState['skill_id'] ?? ''),
            $damage,
        );

        if (! ($logResult['ok'] ?? false)) {
            return false;
        }

        $runtimeState = $logResult['data']['runtime_state'];
        $enemySkillStates = $this->markSkillAsCast($enemySkillStates, $skillState);
        $enemyUnit['skill_states'] = $enemySkillStates;

        return true;
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, mixed>  $unit
     */
    private function ensureUnitSkillStates(array $runtimeState, array $unit): array
    {
        if (is_array($unit['skill_states'] ?? null)) {
            return $this->success([
                'skills' => array_values($unit['skill_states']),
            ]);
        }

        $skillConfigs = $this->resolveUnitSkillConfigs($runtimeState, $unit);
        if ($skillConfigs === []) {
            return $this->success([
                'skills' => [],
            ]);
        }

        $buildResult = $this->skillRuntimeStateBuilder->build($unit, $skillConfigs);
        if (! ($buildResult['ok'] ?? false)) {
            return $this->failure((string) ($buildResult['reason'] ?? 'skill_runtime_build_failed'));
        }

        return $this->success([
            'skills' => array_values($buildResult['data']['skills'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, mixed>  $unit
     * @return array<int, array<string, mixed>>
     */
    private function resolveUnitSkillConfigs(array $runtimeState, array $unit): array
    {
        $configs = [];

        foreach (is_array($unit['skill_configs'] ?? null) ? $unit['skill_configs'] : [] as $row) {
            if (is_array($row)) {
                $configs[] = $row;
            }
        }

        if ($configs !== []) {
            return array_values($configs);
        }

        $contextSkillConfigs = is_array($runtimeState['battle_context']['skill_configs'] ?? null)
            ? $runtimeState['battle_context']['skill_configs']
            : [];

        foreach (is_array($unit['skills'] ?? null) ? $unit['skills'] : [] as $skillRow) {
            if (is_array($skillRow)) {
                $configs[] = $skillRow;

                continue;
            }

            $skillId = trim((string) $skillRow);
            if ($skillId === '') {
                continue;
            }

            $resolved = null;
            if (($contextSkillConfigs[$skillId] ?? null) !== null && is_array($contextSkillConfigs[$skillId])) {
                $resolved = $contextSkillConfigs[$skillId];
            } else {
                foreach ($contextSkillConfigs as $config) {
                    if (! is_array($config)) {
                        continue;
                    }

                    if (trim((string) ($config['skill_id'] ?? '')) === $skillId) {
                        $resolved = $config;
                        break;
                    }
                }
            }

            if (! is_array($resolved)) {
                continue;
            }

            if (trim((string) ($resolved['skill_id'] ?? '')) === '') {
                $resolved['skill_id'] = $skillId;
            }

            $configs[] = $resolved;
        }

        return array_values($configs);
    }

    /**
     * @param  array<int, array<string, mixed>>  $skillStates
     * @param  array<string, mixed>  $castSkillState
     * @return array<int, array<string, mixed>>
     */
    private function markSkillAsCast(array $skillStates, array $castSkillState): array
    {
        $enterCooldownResult = $this->skillCooldownResolver->enterCooldown($castSkillState);
        if (! ($enterCooldownResult['ok'] ?? false)) {
            return $skillStates;
        }

        $updatedState = is_array($enterCooldownResult['data']['skill'] ?? null)
            ? $enterCooldownResult['data']['skill']
            : $castSkillState;
        $castSkillId = trim((string) ($updatedState['skill_id'] ?? $castSkillState['skill_id'] ?? ''));

        foreach ($skillStates as $index => $skillState) {
            if (! is_array($skillState)) {
                continue;
            }

            if (trim((string) ($skillState['skill_id'] ?? '')) !== $castSkillId) {
                continue;
            }

            $skillStates[$index] = $updatedState;

            break;
        }

        return $skillStates;
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function tickSkillCooldowns(array $playerUnit, array $enemyUnits): array
    {
        $playerSkillStates = is_array($playerUnit['skill_states'] ?? null) ? array_values($playerUnit['skill_states']) : [];
        foreach ($playerSkillStates as $index => $skillState) {
            if (! is_array($skillState)) {
                continue;
            }

            $tickResult = $this->skillCooldownResolver->tick($skillState);
            if (! ($tickResult['ok'] ?? false)) {
                return $this->failure((string) ($tickResult['reason'] ?? 'player_skill_cooldown_tick_failed'));
            }

            $playerSkillStates[$index] = $tickResult['data']['skill'];
        }
        $playerUnit['skill_states'] = $playerSkillStates;

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            $enemySkillStates = is_array($enemyUnit['skill_states'] ?? null) ? array_values($enemyUnit['skill_states']) : [];

            foreach ($enemySkillStates as $skillIndex => $skillState) {
                if (! is_array($skillState)) {
                    continue;
                }

                $tickResult = $this->skillCooldownResolver->tick($skillState);
                if (! ($tickResult['ok'] ?? false)) {
                    return $this->failure((string) ($tickResult['reason'] ?? 'enemy_skill_cooldown_tick_failed'));
                }

                $enemySkillStates[$skillIndex] = $tickResult['data']['skill'];
            }

            $enemyUnit['skill_states'] = $enemySkillStates;
            $enemyUnits[$enemyIndex] = $enemyUnit;
        }

        return $this->success([
            'player_unit' => $playerUnit,
            'enemy_units' => $enemyUnits,
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function settleDeaths(array $runtimeState, int $tick, array $playerUnit, array $enemyUnits): array
    {
        $playerWasAlive = ($playerUnit['alive'] ?? false) === true;
        $playerUnit['alive'] = max(0, (int) ($playerUnit['current_hp'] ?? 0)) > 0;

        if ($playerWasAlive && ($playerUnit['alive'] ?? false) !== true) {
            $runtimeState['logs'][] = $this->buildStateLog(
                $tick,
                'unit_dead',
                (string) ($playerUnit['unit_id'] ?? 'player')
            );
        }

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            $enemyWasAlive = ($enemyUnit['alive'] ?? false) === true;
            $enemyUnit['alive'] = max(0, (int) ($enemyUnit['current_hp'] ?? 0)) > 0;

            if ($enemyWasAlive && ($enemyUnit['alive'] ?? false) !== true) {
                $runtimeState['logs'][] = $this->buildStateLog(
                    $tick,
                    'unit_dead',
                    (string) ($enemyUnit['unit_id'] ?? 'enemy')
                );
            }

            $enemyUnits[$enemyIndex] = $enemyUnit;
        }

        return $this->success([
            'runtime_state' => $runtimeState,
            'player_unit' => $playerUnit,
            'enemy_units' => $enemyUnits,
        ]);
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
