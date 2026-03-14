<?php

namespace App\Services\Game\Battle;

class CombatTickRunner
{
    public function __construct(
        private readonly BattleVictoryResolver $battleVictoryResolver = new BattleVictoryResolver(),
        private readonly SkillRuntimeStateBuilder $skillRuntimeStateBuilder = new SkillRuntimeStateBuilder(),
        private readonly SkillCooldownResolver $skillCooldownResolver = new SkillCooldownResolver(),
        private readonly SkillCastResolver $skillCastResolver = new SkillCastResolver(),
        private readonly MultiSkillTypeResolver $multiSkillTypeResolver = new MultiSkillTypeResolver(),
        private readonly DotHotTickResolver $dotHotTickResolver = new DotHotTickResolver(),
        private readonly StatusControlResolver $statusControlResolver = new StatusControlResolver(),
        private readonly ExpandedDamageResolver $expandedDamageResolver = new ExpandedDamageResolver(),
        private readonly DamageActionLogger $damageActionLogger = new DamageActionLogger(),
        private readonly EffectActionLogger $effectActionLogger = new EffectActionLogger(),
        private readonly DotHotEffectLogger $dotHotEffectLogger = new DotHotEffectLogger(),
        private readonly StatusEffectLogger $statusEffectLogger = new StatusEffectLogger(),
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

        if (! $this->processRuntimeEffects($runtimeState, $playerUnit, $enemyUnits, $tick)) {
            return $this->failure('runtime_effect_tick_failed');
        }

        $preActionDeathSettlementResult = $this->settleDeaths($runtimeState, $tick, $playerUnit, $enemyUnits);
        if (! ($preActionDeathSettlementResult['ok'] ?? false)) {
            return $this->failure((string) ($preActionDeathSettlementResult['reason'] ?? 'pre_action_death_settlement_failed'));
        }
        $runtimeState = $preActionDeathSettlementResult['data']['runtime_state'];
        $playerUnit = $preActionDeathSettlementResult['data']['player_unit'];
        $enemyUnits = $preActionDeathSettlementResult['data']['enemy_units'];

        $playerSkillStates = [];
        if ($this->isUnitAvailable($playerUnit)) {
            $playerSkillStateResult = $this->ensureUnitSkillStates($runtimeState, $playerUnit);
            if (! ($playerSkillStateResult['ok'] ?? false)) {
                return $this->failure((string) ($playerSkillStateResult['reason'] ?? 'player_skill_state_build_failed'));
            }
            $playerSkillStates = $playerSkillStateResult['data']['skills'];
            $playerUnit['skill_states'] = $playerSkillStates;
        }

        if ($this->canUnitAct($playerUnit)) {
            $playerCastResult = $this->skillCastResolver->resolvePlayerCast($playerUnit, $enemyUnits, $playerSkillStates);
            if ($this->canUnitCastSkill($playerUnit) && ! ($playerCastResult['ok'] ?? false)) {
                return $this->failure((string) ($playerCastResult['reason'] ?? 'player_skill_cast_resolve_failed'));
            }

            $playerDidCastSkill = false;
            if ($this->canUnitCastSkill($playerUnit) && ($playerCastResult['data']['can_cast'] ?? false) === true) {
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
                    $damageResult = $this->expandedDamageResolver->resolveBasicAttack(
                        $playerUnit,
                        $enemyUnits[$playerTargetIndex],
                        $this->buildAttackContext($tick, 'basic_attack')
                    );
                    if (! ($damageResult['ok'] ?? false)) {
                        return $this->failure((string) ($damageResult['reason'] ?? 'player_attack_resolve_failed'));
                    }

                    $damageData = is_array($damageResult['data'] ?? null) ? $damageResult['data'] : [];
                    $enemyUnits[$playerTargetIndex] = $this->applyDamageToTargetUnit(
                        $enemyUnits[$playerTargetIndex],
                        $damageData
                    );

                    $logResult = $this->damageActionLogger->logDamage(
                        $runtimeState,
                        $tick,
                        (string) ($playerUnit['unit_id'] ?? 'player'),
                        (string) ($enemyUnits[$playerTargetIndex]['unit_id'] ?? 'enemy'),
                        'basic_attack',
                        null,
                        (float) ($damageData['raw_damage'] ?? 0),
                        (bool) ($damageData['is_critical'] ?? false),
                        (float) ($damageData['shield_absorbed'] ?? 0),
                        (float) ($damageData['hp_damage'] ?? 0),
                    );

                    if (! ($logResult['ok'] ?? false)) {
                        return $this->failure((string) ($logResult['reason'] ?? 'player_attack_log_failed'));
                    }

                    $runtimeState = $logResult['data']['runtime_state'];
                }
            }
        }

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            if (
                (int) ($enemyUnit['wave_index'] ?? 0) !== $currentWaveIndex
                || ! $this->isUnitAvailable($enemyUnit)
            ) {
                continue;
            }

            if (! $this->isUnitAvailable($playerUnit)) {
                break;
            }

            $enemySkillStates = [];
            if ($this->isUnitAvailable($enemyUnit)) {
                $enemySkillStateResult = $this->ensureUnitSkillStates($runtimeState, $enemyUnit);
                if (! ($enemySkillStateResult['ok'] ?? false)) {
                    return $this->failure((string) ($enemySkillStateResult['reason'] ?? 'enemy_skill_state_build_failed'));
                }
                $enemySkillStates = $enemySkillStateResult['data']['skills'];
                $enemyUnit['skill_states'] = $enemySkillStates;
            }

            if (! $this->canUnitAct($enemyUnit)) {
                $enemyUnit['skill_states'] = $enemySkillStates;
                $enemyUnits[$enemyIndex] = $enemyUnit;

                continue;
            }

            $enemyCastResult = $this->skillCastResolver->resolveEnemyCast($enemyUnit, $playerUnit, $enemySkillStates);
            if ($this->canUnitCastSkill($enemyUnit) && ! ($enemyCastResult['ok'] ?? false)) {
                return $this->failure((string) ($enemyCastResult['reason'] ?? 'enemy_skill_cast_resolve_failed'));
            }

            $enemyDidCastSkill = false;
            if ($this->canUnitCastSkill($enemyUnit) && ($enemyCastResult['data']['can_cast'] ?? false) === true) {
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
                $damageResult = $this->expandedDamageResolver->resolveBasicAttack(
                    $enemyUnit,
                    $playerUnit,
                    $this->buildAttackContext($tick, 'basic_attack')
                );
                if (! ($damageResult['ok'] ?? false)) {
                    return $this->failure((string) ($damageResult['reason'] ?? 'enemy_attack_resolve_failed'));
                }

                $damageData = is_array($damageResult['data'] ?? null) ? $damageResult['data'] : [];
                $playerUnit = $this->applyDamageToTargetUnit($playerUnit, $damageData);

                $logResult = $this->damageActionLogger->logDamage(
                    $runtimeState,
                    $tick,
                    (string) ($enemyUnit['unit_id'] ?? 'enemy'),
                    (string) ($playerUnit['unit_id'] ?? 'player'),
                    'basic_attack',
                    null,
                    (float) ($damageData['raw_damage'] ?? 0),
                    (bool) ($damageData['is_critical'] ?? false),
                    (float) ($damageData['shield_absorbed'] ?? 0),
                    (float) ($damageData['hp_damage'] ?? 0),
                );

                if (! ($logResult['ok'] ?? false)) {
                    return $this->failure((string) ($logResult['reason'] ?? 'enemy_attack_log_failed'));
                }

                $runtimeState = $logResult['data']['runtime_state'];
            }

            $enemyUnit['skill_states'] = $enemySkillStates;
            $enemyUnits[$enemyIndex] = $enemyUnit;

            if ($this->resolveCurrentHp($playerUnit) <= 0) {
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
        $skillState = is_array($castData['skill'] ?? null) ? $castData['skill'] : null;

        if (! is_array($skillState)) {
            return false;
        }

        $resolveResult = $this->multiSkillTypeResolver->resolve(
            $playerUnit,
            $enemyUnits,
            $skillState,
            $this->buildSkillExecutionContext($tick, $skillState, $castData)
        );
        if (! ($resolveResult['ok'] ?? false)) {
            return false;
        }

        $resolveData = is_array($resolveResult['data'] ?? null) ? $resolveResult['data'] : [];
        $playerUnit = is_array($resolveData['actor_unit'] ?? null) ? $resolveData['actor_unit'] : $playerUnit;
        $enemyUnits = is_array($resolveData['target_units'] ?? null) ? $resolveData['target_units'] : $enemyUnits;

        if (! $this->writeSkillExecutionLogs(
            $runtimeState,
            $tick,
            (string) ($playerUnit['unit_id'] ?? 'player'),
            (string) ($skillState['skill_id'] ?? ''),
            is_array($resolveData['entries'] ?? null) ? $resolveData['entries'] : [],
        )) {
            return false;
        }
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

        $resolveResult = $this->multiSkillTypeResolver->resolve(
            $enemyUnit,
            [$playerUnit],
            $skillState,
            $this->buildSkillExecutionContext($tick, $skillState, ['target_enemy_index' => 0, 'target_enemy_indexes' => [0]])
        );
        if (! ($resolveResult['ok'] ?? false)) {
            return false;
        }

        $resolveData = is_array($resolveResult['data'] ?? null) ? $resolveResult['data'] : [];
        $enemyUnit = is_array($resolveData['actor_unit'] ?? null) ? $resolveData['actor_unit'] : $enemyUnit;
        $playerTargets = is_array($resolveData['target_units'] ?? null) ? $resolveData['target_units'] : [];
        if (is_array($playerTargets[0] ?? null)) {
            $playerUnit = $playerTargets[0];
        }

        if (! $this->writeSkillExecutionLogs(
            $runtimeState,
            $tick,
            (string) ($enemyUnit['unit_id'] ?? 'enemy'),
            (string) ($skillState['skill_id'] ?? ''),
            is_array($resolveData['entries'] ?? null) ? $resolveData['entries'] : [],
        )) {
            return false;
        }
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
        $playerUnit['alive'] = $this->resolveCurrentHp($playerUnit) > 0;

        if ($playerWasAlive && ($playerUnit['alive'] ?? false) !== true) {
            $runtimeState['logs'][] = $this->buildStateLog(
                $tick,
                'unit_dead',
                (string) ($playerUnit['unit_id'] ?? 'player')
            );
        }

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            $enemyWasAlive = ($enemyUnit['alive'] ?? false) === true;
            $enemyUnit['alive'] = $this->resolveCurrentHp($enemyUnit) > 0;

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

    /**
     * @param  array<string, mixed>  $unit
     */
    private function canUnitAct(array $unit): bool
    {
        return $this->isUnitAvailable($unit) && ! $this->hasUnitStatus($unit, 'stunned');
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function canUnitCastSkill(array $unit): bool
    {
        return $this->canUnitAct($unit) && ! $this->hasUnitStatus($unit, 'silenced');
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function isUnitAvailable(array $unit): bool
    {
        return ($unit['alive'] ?? false) === true && $this->resolveCurrentHp($unit) > 0;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function hasUnitStatus(array $unit, string $status): bool
    {
        $safeStatus = trim($status);
        if ($safeStatus === '') {
            return false;
        }

        if (trim((string) ($unit['status'] ?? '')) === $safeStatus) {
            return true;
        }

        foreach (is_array($unit['statuses'] ?? null) ? $unit['statuses'] : [] as $activeStatus) {
            if (trim((string) $activeStatus) === $safeStatus) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $targetUnit
     * @param  array<string, mixed>  $damageData
     */
    private function applyDamageToTargetUnit(array $targetUnit, array $damageData): array
    {
        if (array_key_exists('remaining_shield', $damageData)) {
            $targetUnit['shield'] = $damageData['remaining_shield'];
        }

        if (array_key_exists('remaining_hp', $damageData)) {
            $targetUnit['current_hp'] = $damageData['remaining_hp'];
        } else {
            $targetUnit['current_hp'] = $this->normalizeNumber(
                max(0.0, $this->resolveCurrentHp($targetUnit) - (float) ($damageData['hp_damage'] ?? 0))
            );
        }

        return $targetUnit;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function resolveCurrentHp(array $unit): float
    {
        return max(0.0, (float) ($unit['current_hp'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $attackContext
     * @return array<string, mixed>
     */
    private function buildAttackContext(int $tick, string $actionType, ?string $skillId = null): array
    {
        $attackContext = [
            'tick' => $tick,
            'action_type' => $actionType,
        ];

        if ($skillId !== null && trim($skillId) !== '') {
            $attackContext['skill_id'] = trim($skillId);
        }

        return $attackContext;
    }

    /**
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $castData
     * @return array<string, mixed>
     */
    private function buildSkillExecutionContext(int $tick, array $skillState, array $castData): array
    {
        $context = $this->buildAttackContext($tick, 'skill_cast', (string) ($skillState['skill_id'] ?? ''));
        $context['primary_target_index'] = is_numeric($castData['target_enemy_index'] ?? null)
            ? (int) $castData['target_enemy_index']
            : null;
        $context['target_indexes'] = $this->normalizeTargetIndexes($castData['target_enemy_indexes'] ?? []);

        return $context;
    }

    /**
     * @param  mixed  $targetIndexes
     * @return array<int, int>
     */
    private function normalizeTargetIndexes(mixed $targetIndexes): array
    {
        $normalized = [];

        foreach (is_array($targetIndexes) ? $targetIndexes : [] as $targetIndex) {
            if (! is_numeric($targetIndex)) {
                continue;
            }

            $normalized[] = (int) $targetIndex;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function writeSkillExecutionLogs(
        array &$runtimeState,
        int $tick,
        string $actorUnitId,
        string $skillId,
        array $entries,
    ): bool {
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $logAction = trim((string) ($entry['log_action'] ?? 'skill_cast'));
            if ($logAction === 'effect_apply') {
                $logResult = $this->effectActionLogger->logEffectApply(
                    $runtimeState,
                    $tick,
                    $actorUnitId,
                    trim((string) ($entry['effect_key'] ?? '')),
                    $entry['effect_value'] ?? null,
                    trim((string) ($entry['source'] ?? $skillId))
                );
            } else {
                $extraFields = [];
                if (array_key_exists('hit_index', $entry)) {
                    $extraFields['hit_index'] = max(1, (int) $entry['hit_index']);
                }

                $logResult = $this->damageActionLogger->logDamage(
                    $runtimeState,
                    $tick,
                    $actorUnitId,
                    trim((string) ($entry['target_unit_id'] ?? '')),
                    'skill_cast',
                    $skillId,
                    (float) ($entry['raw_damage'] ?? 0),
                    (bool) ($entry['is_critical'] ?? false),
                    (float) ($entry['shield_absorbed'] ?? 0),
                    (float) ($entry['hp_damage'] ?? 0),
                    $extraFields,
                );
            }

            if (! ($logResult['ok'] ?? false)) {
                return false;
            }

            $runtimeState = $logResult['data']['runtime_state'];
        }

        return true;
    }

    private function normalizeNumber(float $value): int|float
    {
        $rounded = round($value, 3);

        if (abs($rounded - round($rounded)) < 0.000001) {
            return (int) round($rounded);
        }

        return $rounded;
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

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function processRuntimeEffects(
        array &$runtimeState,
        array &$playerUnit,
        array &$enemyUnits,
        int $tick,
    ): bool {
        $runtimeState['player_unit'] = $playerUnit;
        $runtimeState['enemy_units'] = $enemyUnits;

        $statusTickResult = $this->statusControlResolver->tick($runtimeState, $tick);
        if (! ($statusTickResult['ok'] ?? false)) {
            return false;
        }

        $runtimeState = is_array($statusTickResult['data']['runtime_state'] ?? null)
            ? $statusTickResult['data']['runtime_state']
            : $runtimeState;

        foreach (is_array($statusTickResult['data']['tick_results'] ?? null) ? $statusTickResult['data']['tick_results'] : [] as $tickResult) {
            if (! is_array($tickResult)) {
                continue;
            }

            $logResult = $this->statusEffectLogger->logStatus(
                $runtimeState,
                $tick,
                trim((string) ($tickResult['owner_unit_id'] ?? '')),
                trim((string) ($tickResult['target_unit_id'] ?? '')),
                trim((string) ($tickResult['effect_key'] ?? '')),
                trim((string) ($tickResult['status'] ?? '')),
                array_key_exists('status_applied', $tickResult) ? (bool) $tickResult['status_applied'] : null,
                array_key_exists('status_active', $tickResult) ? (bool) $tickResult['status_active'] : null,
                $this->extractRuntimeEffectLogFields($tickResult, [
                    'stack_count',
                    'remaining_ticks',
                    'immune',
                    'resisted',
                    'resistance_pct',
                    'dispelled',
                ]),
            );

            if (! ($logResult['ok'] ?? false)) {
                return false;
            }

            $runtimeState = $logResult['data']['runtime_state'];
        }

        $dotHotTickResult = $this->dotHotTickResolver->resolve($runtimeState, $tick);
        if (! ($dotHotTickResult['ok'] ?? false)) {
            return false;
        }

        $runtimeState = is_array($dotHotTickResult['data']['runtime_state'] ?? null)
            ? $dotHotTickResult['data']['runtime_state']
            : $runtimeState;

        foreach (is_array($dotHotTickResult['data']['tick_results'] ?? null) ? $dotHotTickResult['data']['tick_results'] : [] as $tickResult) {
            if (! is_array($tickResult)) {
                continue;
            }

            $logResult = $this->dotHotEffectLogger->logTick(
                $runtimeState,
                $tick,
                trim((string) ($tickResult['owner_unit_id'] ?? '')),
                trim((string) ($tickResult['target_unit_id'] ?? '')),
                trim((string) ($tickResult['effect_key'] ?? '')),
                trim((string) ($tickResult['effect_type'] ?? '')),
                (float) ($tickResult['hp_damage'] ?? 0),
                (float) ($tickResult['hp_healed'] ?? 0),
                $this->extractRuntimeEffectLogFields($tickResult, [
                    'stack_count',
                    'remaining_ticks',
                    'immune',
                    'resisted',
                    'resistance_pct',
                    'dispelled',
                ]),
            );

            if (! ($logResult['ok'] ?? false)) {
                return false;
            }

            $runtimeState = $logResult['data']['runtime_state'];
        }

        $playerUnit = is_array($runtimeState['player_unit'] ?? null) ? $runtimeState['player_unit'] : $playerUnit;
        $enemyUnits = is_array($runtimeState['enemy_units'] ?? null) ? $runtimeState['enemy_units'] : $enemyUnits;

        return true;
    }

    /**
     * @param  array<string, mixed>  $tickResult
     * @param  array<int, string>  $allowedFields
     * @return array<string, mixed>
     */
    private function extractRuntimeEffectLogFields(array $tickResult, array $allowedFields): array
    {
        $extraFields = [];

        foreach ($allowedFields as $field) {
            $safeField = trim($field);
            if ($safeField === '' || ! array_key_exists($safeField, $tickResult)) {
                continue;
            }

            $extraFields[$safeField] = $tickResult[$safeField];
        }

        return $extraFields;
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
