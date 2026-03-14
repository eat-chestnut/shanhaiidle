<?php

namespace App\Services\Game\Battle;

class StatusControlResolver
{
    private const SUPPORTED_STATUSES = [
        'stunned',
        'slowed',
        'silenced',
    ];

    private const STATUS_PRIORITIES = [
        'stunned' => 300,
        'silenced' => 200,
        'slowed' => 100,
    ];

    private const GENERIC_DURATION_RESIST_KEYS = [
        'cc_duration_down_add_pct',
        'status_duration_down_pct',
        'status_resist_pct',
        'control_resist_pct',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $effects
     * @param  array<string, mixed>  $context
     */
    public function buildStates(array $effects, array $context = []): array
    {
        $runtimeState = is_array($context['runtime_state'] ?? null) ? $context['runtime_state'] : [];
        $existingStates = $this->normalizeExistingStates($context['existing_states'] ?? []);
        $applicationTick = max(0, (int) ($context['tick'] ?? 0));

        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $state = $this->buildStateFromEffect($effect, $runtimeState, $applicationTick);
            if ($state === null) {
                continue;
            }

            $existingStates = $this->mergeState($existingStates, $state);
        }

        $existingStates = array_values(array_map(
            fn (array $state): array => $this->recalculateState($state),
            $existingStates
        ));

        return $this->success([
            'status_control_states' => $existingStates,
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function tick(array $runtimeState, int $tick): array
    {
        $runtimeState['status_control_states'] = $this->normalizeExistingStates($runtimeState['status_control_states'] ?? []);
        $runtimeState['player_unit'] = $this->normalizeUnit($runtimeState['player_unit'] ?? []);
        $runtimeState['enemy_units'] = $this->normalizeUnits($runtimeState['enemy_units'] ?? []);

        $this->clearResolvedStatuses($runtimeState);

        $tickResults = [];
        $statusMap = [];

        foreach ($runtimeState['status_control_states'] as $stateIndex => $state) {
            $status = trim((string) ($state['status'] ?? ''));
            $activeStackIndexes = [];
            $remainingTicks = 0;
            $stackCount = 0;
            $pendingApply = false;
            $resisted = false;
            $resistancePct = 0.0;
            $immune = false;
            $dispelled = false;

            foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stackIndex => $stack) {
                if (! is_array($stack)) {
                    continue;
                }

                if ($this->isActiveStack($stack)) {
                    $activeStackIndexes[] = $stackIndex;
                    $remainingTicks = max($remainingTicks, (int) ($stack['remaining_ticks'] ?? 0));
                    $stackCount++;
                    $pendingApply = $pendingApply || (($stack['applied'] ?? false) !== true);
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
                $this->appendStatusToMap(
                    $statusMap,
                    trim((string) ($state['target_unit_id'] ?? '')),
                    $status,
                    $stackCount
                );

                $tickResult = [
                    'tick' => max(0, $tick),
                    'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                    'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                    'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                    'status' => $status,
                    'stack_count' => $stackCount,
                    'remaining_ticks' => $remainingTicks,
                ];

                if ($pendingApply || ($state['applied'] ?? false) !== true) {
                    $tickResult['status_applied'] = true;
                } else {
                    $tickResult['status_active'] = true;
                }

                if ($resisted) {
                    $tickResult['resisted'] = true;
                    $tickResult['resistance_pct'] = $this->normalizeNumber($resistancePct);
                }

                $runtimeState['status_control_states'][$stateIndex]['applied'] = true;
                $runtimeState['status_control_states'][$stateIndex]['resolved'] = false;

                foreach ($activeStackIndexes as $stackIndex) {
                    $runtimeState['status_control_states'][$stateIndex]['stacks'][$stackIndex]['applied'] = true;
                }
            } elseif (($state['resolved'] ?? false) !== true && (($state['applied'] ?? false) === true || $immune || $dispelled)) {
                $tickResult = [
                    'tick' => max(0, $tick),
                    'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                    'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                    'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                    'status' => $status,
                    'stack_count' => 0,
                    'remaining_ticks' => 0,
                    'status_active' => false,
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

                $runtimeState['status_control_states'][$stateIndex]['resolved'] = true;
                foreach (is_array($runtimeState['status_control_states'][$stateIndex]['stacks'] ?? null) ? $runtimeState['status_control_states'][$stateIndex]['stacks'] : [] as $stackIndex => $stack) {
                    if (! is_array($stack)) {
                        continue;
                    }

                    if (($stack['immune'] ?? false) === true || ($stack['dispelled'] ?? false) === true) {
                        $runtimeState['status_control_states'][$stateIndex]['stacks'][$stackIndex]['resolved'] = true;
                    }
                }
            }

            foreach ($activeStackIndexes as $stackIndex) {
                $stack = $runtimeState['status_control_states'][$stateIndex]['stacks'][$stackIndex];
                if (! is_array($stack)) {
                    continue;
                }

                $runtimeState['status_control_states'][$stateIndex]['stacks'][$stackIndex]['remaining_ticks'] = max(0, (int) ($stack['remaining_ticks'] ?? 0) - 1);
            }

            $runtimeState['status_control_states'][$stateIndex] = $this->recalculateState($runtimeState['status_control_states'][$stateIndex]);

            if (is_array($tickResult)) {
                $tickResults[] = $tickResult;
            }
        }

        $this->applyStatusesToUnits($runtimeState, $statusMap);

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
        ?string $status = null,
        ?string $effectKey = null,
        ?int $maxRemoved = null,
    ): array {
        $runtimeState['status_control_states'] = $this->normalizeExistingStates($runtimeState['status_control_states'] ?? []);
        $runtimeState['player_unit'] = $this->normalizeUnit($runtimeState['player_unit'] ?? []);
        $runtimeState['enemy_units'] = $this->normalizeUnits($runtimeState['enemy_units'] ?? []);

        $safeTargetUnitId = trim($targetUnitId);
        $safeStatus = trim((string) $status);
        $safeEffectKey = trim((string) $effectKey);
        $removedCount = 0;

        if ($safeTargetUnitId === '') {
            return $this->failure('invalid_status_dispel_target');
        }

        foreach ($runtimeState['status_control_states'] as $stateIndex => $state) {
            if (trim((string) ($state['target_unit_id'] ?? '')) !== $safeTargetUnitId) {
                continue;
            }

            if ($safeStatus !== '' && trim((string) ($state['status'] ?? '')) !== $safeStatus) {
                continue;
            }

            if ($safeEffectKey !== '' && trim((string) ($state['effect_key'] ?? '')) !== $safeEffectKey) {
                continue;
            }

            foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stackIndex => $stack) {
                if (! is_array($stack) || ! $this->isActiveStack($stack)) {
                    continue;
                }

                $runtimeState['status_control_states'][$stateIndex]['stacks'][$stackIndex]['remaining_ticks'] = 0;
                $runtimeState['status_control_states'][$stateIndex]['stacks'][$stackIndex]['dispelled'] = true;
                $runtimeState['status_control_states'][$stateIndex]['stacks'][$stackIndex]['resolved'] = false;
                $removedCount++;

                if ($maxRemoved !== null && $removedCount >= max(0, $maxRemoved)) {
                    break 2;
                }
            }
        }

        foreach ($runtimeState['status_control_states'] as $stateIndex => $state) {
            $runtimeState['status_control_states'][$stateIndex] = $this->recalculateState($state);
        }

        $this->clearResolvedStatuses($runtimeState);
        $statusMap = [];
        foreach ($runtimeState['status_control_states'] as $state) {
            $activeCount = max(0, (int) ($state['stack_count'] ?? 0));
            $statusValue = trim((string) ($state['status'] ?? ''));
            $targetId = trim((string) ($state['target_unit_id'] ?? ''));

            if ($activeCount <= 0 || $statusValue === '' || $targetId === '') {
                continue;
            }

            $this->appendStatusToMap($statusMap, $targetId, $statusValue, $activeCount);
        }

        $this->applyStatusesToUnits($runtimeState, $statusMap);

        return $this->success([
            'runtime_state' => $runtimeState,
            'removed_count' => $removedCount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $effect
     * @param  array<string, mixed>  $runtimeState
     * @return array<string, mixed>|null
     */
    private function buildStateFromEffect(array $effect, array $runtimeState, int $applicationTick): ?array
    {
        $effectKey = trim((string) ($effect['effect_key'] ?? ''));
        $ownerUnitId = trim((string) ($effect['owner_unit_id'] ?? ''));
        $targetUnitId = trim((string) ($effect['target_unit_id'] ?? ''));
        $effectType = trim((string) ($effect['effect_type'] ?? ''));
        $triggerTiming = trim((string) ($effect['trigger_timing'] ?? ''));
        $durationTicks = max(0, (int) ($effect['duration_ticks'] ?? 0));
        $status = trim((string) ($effect['status'] ?? ''));

        if (
            $effectKey === ''
            || $ownerUnitId === ''
            || $targetUnitId === ''
            || $effectType !== 'status_control'
            || $triggerTiming !== 'on_apply'
            || $durationTicks <= 0
            || ! in_array($status, self::SUPPORTED_STATUSES, true)
        ) {
            return null;
        }

        $state = [
            'effect_key' => $effectKey,
            'owner_unit_id' => $ownerUnitId,
            'target_unit_id' => $targetUnitId,
            'effect_type' => $effectType,
            'trigger_timing' => $triggerTiming,
            'duration_ticks' => $durationTicks,
            'status' => $status,
            'remaining_ticks' => 0,
            'stack_count' => 0,
            'applied' => false,
            'resolved' => false,
            'stacks' => [],
        ];

        $this->appendOptionalMetadata($state, $effect);

        $targetUnit = $this->resolveTargetUnit($runtimeState, $targetUnitId);
        $targetTags = $this->resolveTargetTags($targetUnit);
        $targetModifiers = $this->resolveTargetModifiers($targetUnit);
        $immune = $this->isStatusImmune($status, $state['immune_tags'] ?? [], $targetTags);
        $durationResistance = $this->resolveDurationResistance($status, $targetModifiers);
        $effectiveDuration = $immune ? 0 : $this->calculateEffectiveDuration($durationTicks, $durationResistance);
        $resisted = $durationResistance > 0.0;

        $state['stacks'][] = [
            'duration_ticks' => $effectiveDuration,
            'remaining_ticks' => $effectiveDuration,
            'applied' => false,
            'immune' => $immune,
            'resisted' => $resisted,
            'resistance_pct' => $this->normalizeNumber($durationResistance),
            'resolved' => false,
            'dispelled' => false,
            'application_tick' => $applicationTick,
            'refresh_count' => 0,
        ];

        return $this->recalculateState($state);
    }

    /**
     * @param  array<int, array<string, mixed>>  $states
     * @param  array<string, mixed>  $candidate
     * @return array<int, array<string, mixed>>
     */
    private function mergeState(array $states, array $candidate): array
    {
        $candidateStack = is_array($candidate['stacks'][0] ?? null) ? $candidate['stacks'][0] : null;
        if ($candidateStack === null) {
            $states[] = $candidate;

            return array_values($states);
        }

        $mergeIndex = $this->findMergeableStateIndex($states, $candidate);
        if ($mergeIndex === null) {
            $states[] = $candidate;

            return array_values($states);
        }

        $state = $states[$mergeIndex];
        $candidateIsPassiveOutcome = ! $this->isActiveStack($candidateStack);

        if ($candidateIsPassiveOutcome) {
            $state['stacks'][] = $candidateStack;
            $state['resolved'] = false;
            $states[$mergeIndex] = $this->recalculateState($state);

            return array_values($states);
        }

        $stacking = (bool) ($state['stacking'] ?? false) || (bool) ($candidate['stacking'] ?? false);
        $refreshable = (bool) ($state['refreshable'] ?? false) || (bool) ($candidate['refreshable'] ?? false);

        if ($stacking) {
            if ($refreshable) {
                $state = $this->refreshActiveStacks($state, $candidateStack);
            }

            $state['stacks'][] = $candidateStack;
            $state['applied'] = false;
            $state['resolved'] = false;
            $states[$mergeIndex] = $this->recalculateState($state);

            return array_values($states);
        }

        if ($refreshable) {
            $refreshed = false;
            foreach ($state['stacks'] as $stackIndex => $stack) {
                if (! is_array($stack) || ! $this->isActiveStack($stack)) {
                    continue;
                }

                $state['stacks'][$stackIndex] = $this->refreshStack($stack, $candidateStack);
                $state['applied'] = false;
                $state['resolved'] = false;
                $refreshed = true;
                break;
            }

            if (! $refreshed) {
                $state['stacks'][] = $candidateStack;
                $state['applied'] = false;
                $state['resolved'] = false;
            }

            $states[$mergeIndex] = $this->recalculateState($state);

            return array_values($states);
        }

        if (! $this->hasActiveStack($state)) {
            $state['stacks'][] = $candidateStack;
            $state['applied'] = false;
            $state['resolved'] = false;
            $states[$mergeIndex] = $this->recalculateState($state);
        }

        return array_values($states);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $candidateStack
     * @return array<string, mixed>
     */
    private function refreshActiveStacks(array $state, array $candidateStack): array
    {
        foreach ($state['stacks'] as $stackIndex => $stack) {
            if (! is_array($stack) || ! $this->isActiveStack($stack)) {
                continue;
            }

            $state['stacks'][$stackIndex] = $this->refreshStack($stack, $candidateStack);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $stack
     * @param  array<string, mixed>  $candidateStack
     * @return array<string, mixed>
     */
    private function refreshStack(array $stack, array $candidateStack): array
    {
        $candidateDuration = max(0, (int) ($candidateStack['duration_ticks'] ?? 0));

        $stack['duration_ticks'] = $candidateDuration;
        $stack['remaining_ticks'] = $candidateDuration;
        $stack['applied'] = false;
        $stack['immune'] = false;
        $stack['dispelled'] = false;
        $stack['resolved'] = false;
        $stack['resisted'] = (bool) ($candidateStack['resisted'] ?? false);
        $stack['resistance_pct'] = $candidateStack['resistance_pct'] ?? 0;
        $stack['application_tick'] = max(0, (int) ($candidateStack['application_tick'] ?? $stack['application_tick'] ?? 0));
        $stack['refresh_count'] = max(0, (int) ($stack['refresh_count'] ?? 0)) + 1;

        return $stack;
    }

    /**
     * @param  mixed  $states
     * @return array<int, array<string, mixed>>
     */
    private function normalizeExistingStates(mixed $states): array
    {
        $normalized = [];

        foreach (is_array($states) ? $states : [] as $state) {
            if (! is_array($state)) {
                continue;
            }

            $status = trim((string) ($state['status'] ?? ''));
            if (! in_array($status, self::SUPPORTED_STATUSES, true)) {
                continue;
            }

            $normalizedState = [
                'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                'effect_type' => trim((string) ($state['effect_type'] ?? 'status_control')),
                'trigger_timing' => trim((string) ($state['trigger_timing'] ?? 'on_apply')),
                'duration_ticks' => max(0, (int) ($state['duration_ticks'] ?? 0)),
                'status' => $status,
                'stacking' => (bool) ($state['stacking'] ?? false),
                'refreshable' => (bool) ($state['refreshable'] ?? false),
                'immune_tags' => $this->normalizeStringList($state['immune_tags'] ?? []),
                'applied' => (bool) ($state['applied'] ?? false),
                'resolved' => (bool) ($state['resolved'] ?? false),
                'stacks' => $this->normalizeStacks($state),
            ];

            if ($normalizedState['effect_key'] === '' || $normalizedState['owner_unit_id'] === '' || $normalizedState['target_unit_id'] === '') {
                continue;
            }

            $normalized[] = $this->recalculateState($normalizedState);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStacks(array $state): array
    {
        $stacks = [];

        foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stack) {
            if (! is_array($stack)) {
                continue;
            }

            $stacks[] = [
                'duration_ticks' => max(0, (int) ($stack['duration_ticks'] ?? $state['duration_ticks'] ?? 0)),
                'remaining_ticks' => max(0, (int) ($stack['remaining_ticks'] ?? $state['remaining_ticks'] ?? 0)),
                'applied' => (bool) ($stack['applied'] ?? false),
                'immune' => (bool) ($stack['immune'] ?? false),
                'resisted' => (bool) ($stack['resisted'] ?? false),
                'resistance_pct' => $this->normalizeNumber($stack['resistance_pct'] ?? 0),
                'resolved' => (bool) ($stack['resolved'] ?? false),
                'dispelled' => (bool) ($stack['dispelled'] ?? false),
                'application_tick' => max(0, (int) ($stack['application_tick'] ?? 0)),
                'refresh_count' => max(0, (int) ($stack['refresh_count'] ?? 0)),
            ];
        }

        if ($stacks !== []) {
            return array_values($stacks);
        }

        return [[
            'duration_ticks' => max(0, (int) ($state['duration_ticks'] ?? 0)),
            'remaining_ticks' => max(0, (int) ($state['remaining_ticks'] ?? $state['duration_ticks'] ?? 0)),
            'applied' => (bool) ($state['applied'] ?? false),
            'immune' => (bool) ($state['immune'] ?? false),
            'resisted' => (bool) ($state['resisted'] ?? false),
            'resistance_pct' => $this->normalizeNumber($state['resistance_pct'] ?? 0),
            'resolved' => (bool) ($state['resolved'] ?? false),
            'dispelled' => (bool) ($state['dispelled'] ?? false),
            'application_tick' => max(0, (int) ($state['application_tick'] ?? 0)),
            'refresh_count' => max(0, (int) ($state['refresh_count'] ?? 0)),
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $states
     */
    private function findMergeableStateIndex(array $states, array $candidate): ?int
    {
        $candidateIdentity = $this->buildStateIdentity($candidate);

        foreach ($states as $index => $state) {
            if (! is_array($state)) {
                continue;
            }

            if ($this->buildStateIdentity($state) !== $candidateIdentity) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function buildStateIdentity(array $state): string
    {
        return implode(':', [
            trim((string) ($state['effect_key'] ?? '')),
            trim((string) ($state['owner_unit_id'] ?? '')),
            trim((string) ($state['target_unit_id'] ?? '')),
            trim((string) ($state['status'] ?? '')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function recalculateState(array $state): array
    {
        $remainingTicks = 0;
        $stackCount = 0;
        $applied = (bool) ($state['applied'] ?? false);
        $resolved = (bool) ($state['resolved'] ?? false);
        $hasPendingResolution = false;
        $resisted = false;
        $resistancePct = 0.0;

        foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stack) {
            if (! is_array($stack)) {
                continue;
            }

            if ($this->isActiveStack($stack)) {
                $remainingTicks = max($remainingTicks, (int) ($stack['remaining_ticks'] ?? 0));
                $stackCount++;
                $applied = $applied || (bool) ($stack['applied'] ?? false);
                $resolved = false;
            }

            $applied = $applied || (bool) ($stack['applied'] ?? false);

            $resisted = $resisted || (bool) ($stack['resisted'] ?? false);
            $resistancePct = max($resistancePct, (float) ($stack['resistance_pct'] ?? 0));

            if (($stack['resolved'] ?? false) !== true && (($stack['immune'] ?? false) === true || ($stack['dispelled'] ?? false) === true)) {
                $hasPendingResolution = true;
                $resolved = false;
            }
        }

        $state['remaining_ticks'] = $remainingTicks;
        $state['stack_count'] = $stackCount;
        $state['applied'] = $applied;
        $state['resolved'] = $stackCount === 0 && ! $hasPendingResolution ? $resolved : false;
        $state['resisted'] = $resisted;
        $state['resistance_pct'] = $this->normalizeNumber($resistancePct);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    private function clearResolvedStatuses(array &$runtimeState): void
    {
        $runtimeState['player_unit']['status'] = null;
        unset($runtimeState['player_unit']['statuses']);

        foreach ($runtimeState['enemy_units'] as $enemyIndex => $enemyUnit) {
            $runtimeState['enemy_units'][$enemyIndex]['status'] = null;
            unset($runtimeState['enemy_units'][$enemyIndex]['statuses']);
        }
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @param  array<string, array<string, int>>  $statusMap
     */
    private function applyStatusesToUnits(array &$runtimeState, array $statusMap): void
    {
        $runtimeState['player_unit'] = $this->applyStatusesToUnit(
            $runtimeState['player_unit'],
            $statusMap[trim((string) ($runtimeState['player_unit']['unit_id'] ?? ''))] ?? []
        );

        foreach ($runtimeState['enemy_units'] as $enemyIndex => $enemyUnit) {
            $runtimeState['enemy_units'][$enemyIndex] = $this->applyStatusesToUnit(
                $enemyUnit,
                $statusMap[trim((string) ($enemyUnit['unit_id'] ?? ''))] ?? []
            );
        }
    }

    /**
     * @param  array<string, mixed>  $unit
     * @param  array<string, int>  $statuses
     * @return array<string, mixed>
     */
    private function applyStatusesToUnit(array $unit, array $statuses): array
    {
        if ($statuses === []) {
            $unit['status'] = null;
            unset($unit['statuses']);

            return $unit;
        }

        $activeStatuses = array_keys(array_filter(
            $statuses,
            static fn (int $count): bool => $count > 0
        ));

        usort($activeStatuses, function (string $left, string $right): int {
            return ($this->resolveStatusPriority($right) <=> $this->resolveStatusPriority($left))
                ?: ($left <=> $right);
        });

        $unit['status'] = $activeStatuses[0] ?? null;
        $unit['statuses'] = $activeStatuses;

        return $unit;
    }

    /**
     * @param  array<string, array<string, int>>  $statusMap
     */
    private function appendStatusToMap(array &$statusMap, string $targetUnitId, string $status, int $count): void
    {
        if ($targetUnitId === '' || $status === '' || $count <= 0) {
            return;
        }

        $statusMap[$targetUnitId] = is_array($statusMap[$targetUnitId] ?? null) ? $statusMap[$targetUnitId] : [];
        $statusMap[$targetUnitId][$status] = max(0, (int) ($statusMap[$targetUnitId][$status] ?? 0)) + $count;
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

    /**
     * @param  array<string, mixed>  $state
     */
    private function hasActiveStack(array $state): bool
    {
        foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stack) {
            if (is_array($stack) && $this->isActiveStack($stack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     * @return array<string, mixed>
     */
    private function resolveTargetUnit(array $runtimeState, string $targetUnitId): array
    {
        $playerUnit = is_array($runtimeState['player_unit'] ?? null) ? $runtimeState['player_unit'] : [];
        if (trim((string) ($playerUnit['unit_id'] ?? '')) === $targetUnitId) {
            return $playerUnit;
        }

        foreach (is_array($runtimeState['enemy_units'] ?? null) ? $runtimeState['enemy_units'] : [] as $enemyUnit) {
            if (! is_array($enemyUnit)) {
                continue;
            }

            if (trim((string) ($enemyUnit['unit_id'] ?? '')) === $targetUnitId) {
                return $enemyUnit;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $unit
     * @return array<string, mixed>
     */
    private function normalizeUnit(mixed $unit): array
    {
        $safeUnit = is_array($unit) ? $unit : [];
        $safeUnit['status'] = is_string($safeUnit['status'] ?? null) ? $safeUnit['status'] : null;

        if (is_array($safeUnit['statuses'] ?? null)) {
            $safeUnit['statuses'] = $this->normalizeStringList($safeUnit['statuses']);
        }

        return $safeUnit;
    }

    /**
     * @param  mixed  $units
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUnits(mixed $units): array
    {
        $normalized = [];

        foreach (is_array($units) ? $units : [] as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $normalized[] = $this->normalizeUnit($unit);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $targetUnit
     * @return array<int, string>
     */
    private function resolveTargetTags(array $targetUnit): array
    {
        return $this->normalizeStringList([
            ...$this->normalizeStringList($targetUnit['tags'] ?? []),
            ...$this->normalizeStringList($targetUnit['runtime_tags'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $targetUnit
     * @return array<string, int|float>
     */
    private function resolveTargetModifiers(array $targetUnit): array
    {
        $modifiers = [];

        foreach (is_array($targetUnit['runtime_modifiers'] ?? null) ? $targetUnit['runtime_modifiers'] : [] as $modifierKey => $modifierValue) {
            $safeModifierKey = trim((string) $modifierKey);
            if ($safeModifierKey === '') {
                continue;
            }

            $modifiers[$safeModifierKey] = $this->normalizeNumber($modifierValue);
        }

        return $modifiers;
    }

    /**
     * @param  array<int, string>  $immuneTags
     * @param  array<int, string>  $targetTags
     */
    private function isStatusImmune(string $status, array $immuneTags, array $targetTags): bool
    {
        $targetTagMap = array_fill_keys($targetTags, true);

        foreach ($immuneTags as $immuneTag) {
            if (isset($targetTagMap[$immuneTag])) {
                return true;
            }
        }

        foreach ([
            'immune_status_control',
            'status_control_immune',
            'immune_'.$status,
            $status.'_immune',
            'immune_cc',
        ] as $immunityTag) {
            if ($immunityTag !== '' && isset($targetTagMap[$immunityTag])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int|float>  $modifiers
     */
    private function resolveDurationResistance(string $status, array $modifiers): float
    {
        $keys = [
            ...self::GENERIC_DURATION_RESIST_KEYS,
            $status.'_duration_down_pct',
        ];

        $value = 0.0;
        foreach ($keys as $key) {
            $value += max(0.0, min(1.0, (float) ($modifiers[$key] ?? 0)));
        }

        return max(0.0, min(1.0, $value));
    }

    private function calculateEffectiveDuration(int $durationTicks, float $resistance): int
    {
        if ($durationTicks <= 0) {
            return 0;
        }

        if ($resistance >= 1.0) {
            return 0;
        }

        return max(1, (int) ceil($durationTicks * (1.0 - $resistance)));
    }

    private function resolveStatusPriority(string $status): int
    {
        return self::STATUS_PRIORITIES[$status] ?? 0;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $effect
     */
    private function appendOptionalMetadata(array &$state, array $effect): void
    {
        $state['stacking'] = (bool) ($effect['stacking'] ?? false);
        $state['refreshable'] = (bool) ($effect['refreshable'] ?? false);
        $state['immune_tags'] = $this->normalizeStringList($effect['immune_tags'] ?? []);
    }

    private function normalizeNumber(mixed $value): int|float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $rounded = round((float) $value, 3);
        if (abs($rounded - round($rounded)) < 0.000001) {
            return (int) round($rounded);
        }

        return $rounded;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        $normalized = [];

        foreach (is_array($value) ? $value : [] as $entry) {
            $safeEntry = trim((string) $entry);
            if ($safeEntry === '') {
                continue;
            }

            $normalized[$safeEntry] = $safeEntry;
        }

        return array_values($normalized);
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
