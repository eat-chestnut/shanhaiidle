<?php

namespace App\Services\Game\Battle;

use Illuminate\Support\Str;

class BattleRuntimeStateBuilder
{
    public function __construct(
        private readonly RuntimeEffectStateBuilder $runtimeEffectStateBuilder = new RuntimeEffectStateBuilder(),
        private readonly PassiveEffectApplier $passiveEffectApplier = new PassiveEffectApplier(),
        private readonly BattleStartEffectApplier $battleStartEffectApplier = new BattleStartEffectApplier(),
        private readonly SpecialEffectTriggerResolver $specialEffectTriggerResolver = new SpecialEffectTriggerResolver(),
        private readonly EffectActionLogger $effectActionLogger = new EffectActionLogger(),
        private readonly DotHotStateBuilder $dotHotStateBuilder = new DotHotStateBuilder(),
        private readonly StatusControlResolver $statusControlResolver = new StatusControlResolver(),
    ) {
    }

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

        $runtimeState = [
            'battle_id' => $this->resolveBattleId($payload, $playerSnapshot),
            'status' => 'running',
            'tick' => 0,
            'current_wave_index' => $this->resolveCurrentWaveIndex($enemyUnits),
            'player_unit' => $playerUnit,
            'enemy_units' => $enemyUnits,
            'battle_context' => $battleContext,
            'dot_hot_states' => [],
            'status_control_states' => [],
            'logs' => [],
        ];

        $playerInitialization = $this->initializeUnitEffects($runtimeState, $runtimeState['player_unit']);
        if (! ($playerInitialization['ok'] ?? false)) {
            return $this->failure((string) ($playerInitialization['reason'] ?? 'player_runtime_effect_initialization_failed'));
        }

        $runtimeState = $playerInitialization['data']['runtime_state'];
        $runtimeState['player_unit'] = $playerInitialization['data']['unit_runtime_state'];

        foreach ($runtimeState['enemy_units'] as $enemyIndex => $enemyUnit) {
            $enemyInitialization = $this->initializeUnitEffects($runtimeState, $enemyUnit);
            if (! ($enemyInitialization['ok'] ?? false)) {
                return $this->failure((string) ($enemyInitialization['reason'] ?? 'enemy_runtime_effect_initialization_failed'));
            }

            $runtimeState = $enemyInitialization['data']['runtime_state'];
            $runtimeState['enemy_units'][$enemyIndex] = $enemyInitialization['data']['unit_runtime_state'];
        }

        $timedEffectInitialization = $this->initializeTimedEffectStates($runtimeState);
        if (! ($timedEffectInitialization['ok'] ?? false)) {
            return $this->failure((string) ($timedEffectInitialization['reason'] ?? 'timed_effect_state_initialization_failed'));
        }

        $runtimeState = $timedEffectInitialization['data']['runtime_state'];

        return $this->success($runtimeState);
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
            'skills' => is_array($playerSnapshot['skills'] ?? null) ? array_values($playerSnapshot['skills']) : [],
            'special_effects' => is_array($playerSnapshot['special_effects'] ?? null) ? array_values($playerSnapshot['special_effects']) : [],
            'runtime_effects' => [],
            'runtime_modifiers' => [],
            'runtime_tags' => [],
            'shield' => 0,
            'status' => null,
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

        $unit = [
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
            'runtime_effects' => [],
            'runtime_modifiers' => [],
            'runtime_tags' => [],
            'shield' => 0,
            'status' => null,
            'alive' => $maxHp > 0,
        ];

        if (is_array($enemySnapshot['special_effects'] ?? null)) {
            $unit['special_effects'] = array_values($enemySnapshot['special_effects']);
        }

        return $unit;
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, mixed>  $unitRuntimeState
     */
    private function initializeUnitEffects(array $runtimeState, array $unitRuntimeState): array
    {
        $unitId = trim((string) ($unitRuntimeState['unit_id'] ?? ''));
        if ($unitId === '') {
            return $this->failure('invalid_runtime_unit');
        }

        $runtimeState['logs'] = is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [];
        $unitRuntimeState['runtime_tags'] = is_array($unitRuntimeState['runtime_tags'] ?? null) ? array_values($unitRuntimeState['runtime_tags']) : [];
        $unitRuntimeState['runtime_modifiers'] = is_array($unitRuntimeState['runtime_modifiers'] ?? null) ? $unitRuntimeState['runtime_modifiers'] : [];
        $unitRuntimeState['shield'] = max(0, (int) ($unitRuntimeState['shield'] ?? 0));

        $runtimeEffectBuildResult = $this->runtimeEffectStateBuilder->build(
            $unitId,
            is_array($unitRuntimeState['special_effects'] ?? null) ? array_values($unitRuntimeState['special_effects']) : []
        );

        if (! ($runtimeEffectBuildResult['ok'] ?? false)) {
            return $this->failure((string) ($runtimeEffectBuildResult['reason'] ?? 'runtime_effect_state_build_failed'));
        }

        $unitRuntimeState['runtime_effects'] = is_array($runtimeEffectBuildResult['data']['runtime_effects'] ?? null)
            ? array_values($runtimeEffectBuildResult['data']['runtime_effects'])
            : [];

        $passiveEffectsResult = $this->specialEffectTriggerResolver->resolveByTiming(
            $unitRuntimeState['runtime_effects'],
            'passive_always'
        );
        if (! ($passiveEffectsResult['ok'] ?? false)) {
            return $this->failure((string) ($passiveEffectsResult['reason'] ?? 'passive_effect_resolve_failed'));
        }

        $passiveApplyResult = $this->passiveEffectApplier->apply(
            $unitRuntimeState,
            $passiveEffectsResult['data']['runtime_effects'] ?? []
        );
        if (! ($passiveApplyResult['ok'] ?? false)) {
            return $this->failure((string) ($passiveApplyResult['reason'] ?? 'passive_effect_apply_failed'));
        }

        $unitRuntimeState = is_array($passiveApplyResult['data']['unit_runtime_state'] ?? null)
            ? $passiveApplyResult['data']['unit_runtime_state']
            : $unitRuntimeState;

        $passiveLogsResult = $this->writeEffectApplyLogs(
            $runtimeState,
            0,
            $unitId,
            $passiveApplyResult['data']['applied_effects'] ?? []
        );
        if (! ($passiveLogsResult['ok'] ?? false)) {
            return $this->failure((string) ($passiveLogsResult['reason'] ?? 'passive_effect_log_failed'));
        }

        $runtimeState = $passiveLogsResult['data']['runtime_state'];

        $battleStartEffectsResult = $this->specialEffectTriggerResolver->resolveByTiming(
            $unitRuntimeState['runtime_effects'],
            'on_battle_start'
        );
        if (! ($battleStartEffectsResult['ok'] ?? false)) {
            return $this->failure((string) ($battleStartEffectsResult['reason'] ?? 'battle_start_effect_resolve_failed'));
        }

        $battleStartApplyResult = $this->battleStartEffectApplier->apply(
            $unitRuntimeState,
            $battleStartEffectsResult['data']['runtime_effects'] ?? []
        );
        if (! ($battleStartApplyResult['ok'] ?? false)) {
            return $this->failure((string) ($battleStartApplyResult['reason'] ?? 'battle_start_effect_apply_failed'));
        }

        $unitRuntimeState = is_array($battleStartApplyResult['data']['unit_runtime_state'] ?? null)
            ? $battleStartApplyResult['data']['unit_runtime_state']
            : $unitRuntimeState;

        $battleStartLogsResult = $this->writeEffectApplyLogs(
            $runtimeState,
            0,
            $unitId,
            $battleStartApplyResult['data']['applied_effects'] ?? []
        );
        if (! ($battleStartLogsResult['ok'] ?? false)) {
            return $this->failure((string) ($battleStartLogsResult['reason'] ?? 'battle_start_effect_log_failed'));
        }

        $runtimeState = $battleStartLogsResult['data']['runtime_state'];

        return $this->success([
            'runtime_state' => $runtimeState,
            'unit_runtime_state' => $unitRuntimeState,
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    private function initializeTimedEffectStates(array $runtimeState): array
    {
        $timedEffects = $this->collectTimedEffects($runtimeState['player_unit'], $runtimeState['enemy_units']);

        $dotHotBuildResult = $this->dotHotStateBuilder->build($timedEffects, [
            'runtime_state' => $runtimeState,
            'existing_states' => $runtimeState['dot_hot_states'] ?? [],
            'tick' => 0,
        ]);
        if (! ($dotHotBuildResult['ok'] ?? false)) {
            return $this->failure((string) ($dotHotBuildResult['reason'] ?? 'dot_hot_state_build_failed'));
        }

        $statusBuildResult = $this->statusControlResolver->buildStates($timedEffects, [
            'runtime_state' => $runtimeState,
            'existing_states' => $runtimeState['status_control_states'] ?? [],
            'tick' => 0,
        ]);
        if (! ($statusBuildResult['ok'] ?? false)) {
            return $this->failure((string) ($statusBuildResult['reason'] ?? 'status_control_state_build_failed'));
        }

        $runtimeState['dot_hot_states'] = is_array($dotHotBuildResult['data']['dot_hot_states'] ?? null)
            ? array_values($dotHotBuildResult['data']['dot_hot_states'])
            : [];
        $runtimeState['status_control_states'] = is_array($statusBuildResult['data']['status_control_states'] ?? null)
            ? array_values($statusBuildResult['data']['status_control_states'])
            : [];

        return $this->success([
            'runtime_state' => $runtimeState,
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  mixed  $appliedEffects
     */
    private function writeEffectApplyLogs(array $runtimeState, int $tick, string $actorUnitId, mixed $appliedEffects): array
    {
        foreach (is_array($appliedEffects) ? $appliedEffects : [] as $appliedEffect) {
            if (! is_array($appliedEffect)) {
                continue;
            }

            $logResult = $this->effectActionLogger->logEffectApply(
                $runtimeState,
                $tick,
                $actorUnitId,
                trim((string) ($appliedEffect['effect_key'] ?? '')),
                $appliedEffect['value'] ?? null,
                trim((string) ($appliedEffect['source'] ?? ''))
            );

            if (! ($logResult['ok'] ?? false)) {
                return $this->failure((string) ($logResult['reason'] ?? 'effect_apply_log_failed'));
            }

            $runtimeState = $logResult['data']['runtime_state'];
        }

        return $this->success([
            'runtime_state' => $runtimeState,
        ]);
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @return array<int, array<string, mixed>>
     */
    private function collectTimedEffects(array $playerUnit, array $enemyUnits): array
    {
        $timedEffects = [];

        foreach ([$playerUnit, ...$enemyUnits] as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $ownerUnitId = trim((string) ($unit['unit_id'] ?? ''));

            foreach (is_array($unit['special_effects'] ?? null) ? $unit['special_effects'] : [] as $effect) {
                if (! is_array($effect)) {
                    continue;
                }

                if (trim((string) ($effect['owner_unit_id'] ?? '')) === '' && $ownerUnitId !== '') {
                    $effect['owner_unit_id'] = $ownerUnitId;
                }

                $timedEffects[] = $effect;
            }
        }

        return array_values($timedEffects);
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
