<?php

namespace App\Services\Game\Battle;

class DotHotStateBuilder
{
    private const SUPPORTED_EFFECT_TYPES = [
        'dot',
        'hot',
    ];

    private const DOT_VALUE_RESIST_KEYS = [
        'dot_damage_taken_down_pct',
        'dot_resist_pct',
        'periodic_damage_taken_down_pct',
    ];

    private const HOT_VALUE_RESIST_KEYS = [
        'hot_healing_received_down_pct',
        'hot_resist_pct',
        'healing_received_down_pct',
    ];

    private const DOT_DURATION_RESIST_KEYS = [
        'dot_duration_down_pct',
        'periodic_duration_down_pct',
        'debuff_duration_down_pct',
    ];

    private const HOT_DURATION_RESIST_KEYS = [
        'hot_duration_down_pct',
        'periodic_duration_down_pct',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $effects
     * @param  array<string, mixed>  $context
     */
    public function build(array $effects, array $context = []): array
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
            'dot_hot_states' => $existingStates,
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

        if (
            $effectKey === ''
            || $ownerUnitId === ''
            || $targetUnitId === ''
            || ! in_array($effectType, self::SUPPORTED_EFFECT_TYPES, true)
            || $triggerTiming !== 'per_tick'
            || $durationTicks <= 0
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
        $immune = $this->isEffectImmune($effectType, $state['immune_tags'] ?? [], $targetTags);

        $value = 0.0;
        $valueField = $effectType === 'dot' ? 'damage_per_tick' : 'healing_per_tick';
        if ($effectType === 'dot') {
            $value = max(0.0, (float) $this->normalizeNumber($effect['damage_per_tick'] ?? 0));
        }

        if ($effectType === 'hot') {
            $value = max(0.0, (float) $this->normalizeNumber($effect['healing_per_tick'] ?? 0));
        }

        if ($value <= 0) {
            return null;
        }

        $valueResistance = $this->resolveValueResistance($effectType, $targetModifiers);
        $durationResistance = $this->resolveDurationResistance($effectType, $targetModifiers);

        $effectiveValue = $immune ? 0.0 : $value * (1.0 - $valueResistance);
        $effectiveDuration = $immune ? 0 : $this->calculateEffectiveDuration($durationTicks, $durationResistance);
        $resisted = $valueResistance > 0.0 || $durationResistance > 0.0;

        $stack = [
            'duration_ticks' => $effectiveDuration,
            'remaining_ticks' => $effectiveDuration,
            'immune' => $immune,
            'resisted' => $resisted,
            'resistance_pct' => $this->normalizeNumber(max($valueResistance, $durationResistance)),
            'resolved' => false,
            'dispelled' => false,
            'application_tick' => $applicationTick,
            'refresh_count' => 0,
        ];

        if (! $immune && $effectiveValue > 0.0 && $effectiveDuration > 0) {
            $stack[$valueField] = $this->normalizeNumber($effectiveValue);
        } else {
            $stack[$valueField] = 0;
        }

        $state['stacks'][] = $stack;

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

                $state['stacks'][$stackIndex] = $this->refreshStack($stack, $candidateStack, (string) ($state['effect_type'] ?? ''));
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

            $state['stacks'][$stackIndex] = $this->refreshStack($stack, $candidateStack, (string) ($state['effect_type'] ?? ''));
        }

        $state['applied'] = false;
        $state['resolved'] = false;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $stack
     * @param  array<string, mixed>  $candidateStack
     * @return array<string, mixed>
     */
    private function refreshStack(array $stack, array $candidateStack, string $effectType): array
    {
        $safeEffectType = trim($effectType);
        $valueField = $safeEffectType === 'hot' ? 'healing_per_tick' : 'damage_per_tick';
        $candidateDuration = max(0, (int) ($candidateStack['duration_ticks'] ?? 0));

        $stack['duration_ticks'] = $candidateDuration;
        $stack['remaining_ticks'] = $candidateDuration;
        $stack[$valueField] = $candidateStack[$valueField] ?? $stack[$valueField] ?? 0;
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

            $effectType = trim((string) ($state['effect_type'] ?? ''));
            if (! in_array($effectType, self::SUPPORTED_EFFECT_TYPES, true)) {
                continue;
            }

            $normalizedState = [
                'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                'effect_type' => $effectType,
                'trigger_timing' => trim((string) ($state['trigger_timing'] ?? 'per_tick')),
                'duration_ticks' => max(0, (int) ($state['duration_ticks'] ?? 0)),
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
        $effectType = trim((string) ($state['effect_type'] ?? ''));
        $valueField = $effectType === 'hot' ? 'healing_per_tick' : 'damage_per_tick';
        $stacks = [];

        foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stack) {
            if (! is_array($stack)) {
                continue;
            }

            $normalizedStack = [
                'duration_ticks' => max(0, (int) ($stack['duration_ticks'] ?? $state['duration_ticks'] ?? 0)),
                'remaining_ticks' => max(0, (int) ($stack['remaining_ticks'] ?? $state['remaining_ticks'] ?? 0)),
                'immune' => (bool) ($stack['immune'] ?? false),
                'resisted' => (bool) ($stack['resisted'] ?? false),
                'resistance_pct' => $this->normalizeNumber($stack['resistance_pct'] ?? 0),
                'resolved' => (bool) ($stack['resolved'] ?? false),
                'dispelled' => (bool) ($stack['dispelled'] ?? false),
                'application_tick' => max(0, (int) ($stack['application_tick'] ?? 0)),
                'refresh_count' => max(0, (int) ($stack['refresh_count'] ?? 0)),
                $valueField => max(0, $this->normalizeNumber($stack[$valueField] ?? 0)),
            ];

            $stacks[] = $normalizedStack;
        }

        if ($stacks !== []) {
            return array_values($stacks);
        }

        return [[
            'duration_ticks' => max(0, (int) ($state['duration_ticks'] ?? 0)),
            'remaining_ticks' => max(0, (int) ($state['remaining_ticks'] ?? $state['duration_ticks'] ?? 0)),
            'immune' => (bool) ($state['immune'] ?? false),
            'resisted' => (bool) ($state['resisted'] ?? false),
            'resistance_pct' => $this->normalizeNumber($state['resistance_pct'] ?? 0),
            'resolved' => (bool) ($state['resolved'] ?? false),
            'dispelled' => (bool) ($state['dispelled'] ?? false),
            'application_tick' => max(0, (int) ($state['application_tick'] ?? 0)),
            'refresh_count' => max(0, (int) ($state['refresh_count'] ?? 0)),
            $valueField => max(0, $this->normalizeNumber($state[$valueField] ?? 0)),
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
            trim((string) ($state['effect_type'] ?? '')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function recalculateState(array $state): array
    {
        $effectType = trim((string) ($state['effect_type'] ?? ''));
        $valueField = $effectType === 'hot' ? 'healing_per_tick' : 'damage_per_tick';
        $valueTotal = 0.0;
        $remainingTicks = 0;
        $stackCount = 0;
        $applied = false;
        $resolved = (bool) ($state['resolved'] ?? false);
        $hasPendingResolution = false;
        $resisted = false;
        $resistancePct = 0.0;

        foreach (is_array($state['stacks'] ?? null) ? $state['stacks'] : [] as $stack) {
            if (! is_array($stack)) {
                continue;
            }

            if ($this->isActiveStack($stack)) {
                $stackCount++;
                $remainingTicks = max($remainingTicks, (int) ($stack['remaining_ticks'] ?? 0));
                $valueTotal += (float) ($stack[$valueField] ?? 0);
                $applied = true;
                $resolved = false;
            }

            $resisted = $resisted || (bool) ($stack['resisted'] ?? false);
            $resistancePct = max($resistancePct, (float) ($stack['resistance_pct'] ?? 0));

            if (($stack['resolved'] ?? false) !== true && (! empty($stack['immune']) || ! empty($stack['dispelled']))) {
                $hasPendingResolution = true;
                $resolved = false;
            }
        }

        $state['stack_count'] = $stackCount;
        $state['remaining_ticks'] = $remainingTicks;
        $state[$valueField] = $this->normalizeNumber($valueTotal);
        $state['applied'] = $applied;
        $state['resolved'] = $stackCount === 0 && ! $hasPendingResolution ? $resolved : false;
        $state['resisted'] = $resisted;
        $state['resistance_pct'] = $this->normalizeNumber($resistancePct);

        if ($effectType === 'dot' && ! array_key_exists('healing_per_tick', $state)) {
            $state['healing_per_tick'] = 0;
        }

        if ($effectType === 'hot' && ! array_key_exists('damage_per_tick', $state)) {
            $state['damage_per_tick'] = 0;
        }

        return $state;
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
    private function isEffectImmune(string $effectType, array $immuneTags, array $targetTags): bool
    {
        $safeEffectType = trim($effectType);
        $targetTagMap = array_fill_keys($targetTags, true);

        foreach ($immuneTags as $immuneTag) {
            if (isset($targetTagMap[$immuneTag])) {
                return true;
            }
        }

        foreach ([
            'immune_'.$safeEffectType,
            $safeEffectType.'_immune',
            'immune_periodic',
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
    private function resolveValueResistance(string $effectType, array $modifiers): float
    {
        $keys = $effectType === 'hot'
            ? self::HOT_VALUE_RESIST_KEYS
            : self::DOT_VALUE_RESIST_KEYS;

        return $this->resolveResistanceByKeys($modifiers, $keys);
    }

    /**
     * @param  array<string, int|float>  $modifiers
     */
    private function resolveDurationResistance(string $effectType, array $modifiers): float
    {
        $keys = $effectType === 'hot'
            ? self::HOT_DURATION_RESIST_KEYS
            : self::DOT_DURATION_RESIST_KEYS;

        return $this->resolveResistanceByKeys($modifiers, $keys);
    }

    /**
     * @param  array<string, int|float>  $modifiers
     * @param  array<int, string>  $keys
     */
    private function resolveResistanceByKeys(array $modifiers, array $keys): float
    {
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

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
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
}
