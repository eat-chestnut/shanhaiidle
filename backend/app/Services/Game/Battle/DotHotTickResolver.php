<?php

namespace App\Services\Game\Battle;

class DotHotTickResolver
{
    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function resolve(array $runtimeState, int $tick): array
    {
        $runtimeState['dot_hot_states'] = $this->normalizeStates($runtimeState['dot_hot_states'] ?? []);
        $runtimeState['player_unit'] = is_array($runtimeState['player_unit'] ?? null) ? $runtimeState['player_unit'] : [];
        $runtimeState['enemy_units'] = $this->normalizeUnits($runtimeState['enemy_units'] ?? []);

        $tickResults = [];

        foreach ($runtimeState['dot_hot_states'] as $stateIndex => $state) {
            $effectType = trim((string) ($state['effect_type'] ?? ''));
            $valueField = $effectType === 'hot' ? 'healing_per_tick' : 'damage_per_tick';
            $activeStackIndexes = [];
            $valueTotal = 0.0;
            $remainingTicks = 0;
            $stackCount = 0;
            $immune = false;
            $dispelled = false;
            $resisted = false;
            $resistancePct = 0.0;

            foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stackIndex => $stack) {
                if (! is_array($stack)) {
                    continue;
                }

                if ($this->isActiveStack($stack)) {
                    $activeStackIndexes[] = $stackIndex;
                    $valueTotal += max(0.0, (float) ($stack[$valueField] ?? 0));
                    $remainingTicks = max($remainingTicks, (int) ($stack['remaining_ticks'] ?? 0));
                    $stackCount++;
                    $resisted = $resisted || (bool) ($stack['resisted'] ?? false);
                    $resistancePct = max($resistancePct, (float) ($stack['resistance_pct'] ?? 0));
                    continue;
                }

                if (($stack['resolved'] ?? false) !== true && (($stack['immune'] ?? false) === true || ($stack['dispelled'] ?? false) === true)) {
                    $immune = $immune || (bool) ($stack['immune'] ?? false);
                    $dispelled = $dispelled || (bool) ($stack['dispelled'] ?? false);
                    $resisted = $resisted || (bool) ($stack['resisted'] ?? false);
                    $resistancePct = max($resistancePct, (float) ($stack['resistance_pct'] ?? 0));
                }
            }

            $tickResult = null;
            if ($stackCount > 0) {
                $tickResult = [
                    'tick' => max(0, $tick),
                    'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                    'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                    'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                    'effect_type' => $effectType,
                    'hp_damage' => 0,
                    'hp_healed' => 0,
                    'stack_count' => $stackCount,
                    'remaining_ticks' => $remainingTicks,
                ];

                if ($resisted) {
                    $tickResult['resisted'] = true;
                    $tickResult['resistance_pct'] = $this->normalizeNumber($resistancePct);
                }

                $targetResolveResult = $this->resolveTargetUnit(
                    $runtimeState['player_unit'],
                    $runtimeState['enemy_units'],
                    $tickResult['target_unit_id']
                );

                if ($effectType === 'dot' && $targetResolveResult['target_type'] !== null) {
                    $tickResult['hp_damage'] = $this->applyDotDamage(
                        $runtimeState,
                        $targetResolveResult['target_type'],
                        $targetResolveResult['target_index'],
                        $valueTotal
                    );
                }

                if ($effectType === 'hot' && $targetResolveResult['target_type'] !== null) {
                    $tickResult['hp_healed'] = $this->applyHotHealing(
                        $runtimeState,
                        $targetResolveResult['target_type'],
                        $targetResolveResult['target_index'],
                        $valueTotal
                    );
                }
            } elseif ($immune || $dispelled) {
                $tickResult = [
                    'tick' => max(0, $tick),
                    'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                    'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                    'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                    'effect_type' => $effectType,
                    'hp_damage' => 0,
                    'hp_healed' => 0,
                    'stack_count' => 0,
                    'remaining_ticks' => 0,
                ];

                if ($immune) {
                    $tickResult['immune'] = true;
                }

                if ($dispelled) {
                    $tickResult['dispelled'] = true;
                }

                if ($resisted) {
                    $tickResult['resisted'] = true;
                    $tickResult['resistance_pct'] = $this->normalizeNumber($resistancePct);
                }
            }

            foreach ($activeStackIndexes as $stackIndex) {
                $stack = $runtimeState['dot_hot_states'][$stateIndex]['stacks'][$stackIndex];
                if (! is_array($stack)) {
                    continue;
                }

                $runtimeState['dot_hot_states'][$stateIndex]['stacks'][$stackIndex]['remaining_ticks'] = max(0, (int) ($stack['remaining_ticks'] ?? 0) - 1);
            }

            if ($stackCount === 0) {
                foreach (is_array($runtimeState['dot_hot_states'][$stateIndex]['stacks'] ?? null) ? $runtimeState['dot_hot_states'][$stateIndex]['stacks'] : [] as $stackIndex => $stack) {
                    if (! is_array($stack)) {
                        continue;
                    }

                    if (($stack['resolved'] ?? false) === true) {
                        continue;
                    }

                    if (($stack['immune'] ?? false) === true || ($stack['dispelled'] ?? false) === true) {
                        $runtimeState['dot_hot_states'][$stateIndex]['stacks'][$stackIndex]['resolved'] = true;
                    }
                }
            }

            $runtimeState['dot_hot_states'][$stateIndex] = $this->recalculateState($runtimeState['dot_hot_states'][$stateIndex]);

            if (is_array($tickResult)) {
                $tickResults[] = $tickResult;
            }
        }

        return $this->success([
            'runtime_state' => $runtimeState,
            'tick_results' => array_values($tickResults),
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function dispel(
        array $runtimeState,
        string $targetUnitId,
        ?string $effectType = null,
        ?string $effectKey = null,
        ?int $maxRemoved = null,
    ): array {
        $runtimeState['dot_hot_states'] = $this->normalizeStates($runtimeState['dot_hot_states'] ?? []);
        $safeTargetUnitId = trim($targetUnitId);
        $safeEffectType = trim((string) $effectType);
        $safeEffectKey = trim((string) $effectKey);
        $removedCount = 0;

        if ($safeTargetUnitId === '') {
            return $this->failure('invalid_dispel_target');
        }

        foreach ($runtimeState['dot_hot_states'] as $stateIndex => $state) {
            if (trim((string) ($state['target_unit_id'] ?? '')) !== $safeTargetUnitId) {
                continue;
            }

            if ($safeEffectType !== '' && trim((string) ($state['effect_type'] ?? '')) !== $safeEffectType) {
                continue;
            }

            if ($safeEffectKey !== '' && trim((string) ($state['effect_key'] ?? '')) !== $safeEffectKey) {
                continue;
            }

            foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stackIndex => $stack) {
                if (! is_array($stack) || ! $this->isActiveStack($stack)) {
                    continue;
                }

                $runtimeState['dot_hot_states'][$stateIndex]['stacks'][$stackIndex]['remaining_ticks'] = 0;
                $runtimeState['dot_hot_states'][$stateIndex]['stacks'][$stackIndex]['dispelled'] = true;
                $runtimeState['dot_hot_states'][$stateIndex]['stacks'][$stackIndex]['resolved'] = false;
                $removedCount++;

                if ($maxRemoved !== null && $removedCount >= max(0, $maxRemoved)) {
                    break 2;
                }
            }
        }

        foreach ($runtimeState['dot_hot_states'] as $stateIndex => $state) {
            $runtimeState['dot_hot_states'][$stateIndex] = $this->recalculateState($state);
        }

        return $this->success([
            'runtime_state' => $runtimeState,
            'removed_count' => $removedCount,
        ]);
    }

    /**
     * @param  mixed  $states
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStates(mixed $states): array
    {
        $builder = new DotHotStateBuilder();
        $buildResult = $builder->build([], ['existing_states' => is_array($states) ? $states : []]);

        return is_array($buildResult['data']['dot_hot_states'] ?? null)
            ? array_values($buildResult['data']['dot_hot_states'])
            : [];
    }

    /**
     * @param  mixed  $units
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUnits(mixed $units): array
    {
        return array_values(array_filter(
            is_array($units) ? $units : [],
            static fn (mixed $unit): bool => is_array($unit)
        ));
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @return array{target_type: string|null, target_index: int|null}
     */
    private function resolveTargetUnit(array $playerUnit, array $enemyUnits, string $targetUnitId): array
    {
        if (trim((string) ($playerUnit['unit_id'] ?? '')) === $targetUnitId) {
            return [
                'target_type' => 'player',
                'target_index' => null,
            ];
        }

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            if (trim((string) ($enemyUnit['unit_id'] ?? '')) !== $targetUnitId) {
                continue;
            }

            return [
                'target_type' => 'enemy',
                'target_index' => $enemyIndex,
            ];
        }

        return [
            'target_type' => null,
            'target_index' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    private function applyDotDamage(array &$runtimeState, string $targetType, ?int $targetIndex, float $damagePerTick): int|float
    {
        if ($targetType === 'player') {
            $currentHp = max(0.0, (float) ($runtimeState['player_unit']['current_hp'] ?? 0));
            $appliedDamage = min($currentHp, max(0.0, $damagePerTick));
            $runtimeState['player_unit']['current_hp'] = $this->normalizeNumber($currentHp - $appliedDamage);

            return $this->normalizeNumber($appliedDamage);
        }

        if ($targetType === 'enemy' && $targetIndex !== null && isset($runtimeState['enemy_units'][$targetIndex])) {
            $currentHp = max(0.0, (float) ($runtimeState['enemy_units'][$targetIndex]['current_hp'] ?? 0));
            $appliedDamage = min($currentHp, max(0.0, $damagePerTick));
            $runtimeState['enemy_units'][$targetIndex]['current_hp'] = $this->normalizeNumber($currentHp - $appliedDamage);

            return $this->normalizeNumber($appliedDamage);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    private function applyHotHealing(array &$runtimeState, string $targetType, ?int $targetIndex, float $healingPerTick): int|float
    {
        if ($targetType === 'player') {
            $currentHp = max(0.0, (float) ($runtimeState['player_unit']['current_hp'] ?? 0));
            $maxHp = max($currentHp, (float) ($runtimeState['player_unit']['max_hp'] ?? $currentHp));
            $appliedHealing = min(max(0.0, $healingPerTick), max(0.0, $maxHp - $currentHp));
            $runtimeState['player_unit']['current_hp'] = $this->normalizeNumber($currentHp + $appliedHealing);

            return $this->normalizeNumber($appliedHealing);
        }

        if ($targetType === 'enemy' && $targetIndex !== null && isset($runtimeState['enemy_units'][$targetIndex])) {
            $currentHp = max(0.0, (float) ($runtimeState['enemy_units'][$targetIndex]['current_hp'] ?? 0));
            $maxHp = max($currentHp, (float) ($runtimeState['enemy_units'][$targetIndex]['max_hp'] ?? $currentHp));
            $appliedHealing = min(max(0.0, $healingPerTick), max(0.0, $maxHp - $currentHp));
            $runtimeState['enemy_units'][$targetIndex]['current_hp'] = $this->normalizeNumber($currentHp + $appliedHealing);

            return $this->normalizeNumber($appliedHealing);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function recalculateState(array $state): array
    {
        $builder = new DotHotStateBuilder();
        $buildResult = $builder->build([], ['existing_states' => [$state]]);

        return is_array($buildResult['data']['dot_hot_states'][0] ?? null)
            ? $buildResult['data']['dot_hot_states'][0]
            : $state;
    }

    /**
     * @param  array<string, mixed>  $stack
     */
    private function isActiveStack(array $stack): bool
    {
        return max(0, (int) ($stack['remaining_ticks'] ?? 0)) > 0
            && ($stack['immune'] ?? false) !== true
            && ($stack['dispelled'] ?? false) !== true;
    }

    private function normalizeNumber(float $value): int|float
    {
        $rounded = round($value, 3);

        if (abs($rounded - round($rounded)) < 0.000001) {
            return (int) round($rounded);
        }

        return $rounded;
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
