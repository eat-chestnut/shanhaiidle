<?php

namespace App\Services\Game\Battle;

class MultiSkillTypeResolver
{
    public function __construct(
        private readonly ExpandedDamageResolver $expandedDamageResolver = new ExpandedDamageResolver(),
        private readonly MultiHitDamageResolver $multiHitDamageResolver = new MultiHitDamageResolver(),
        private readonly AoEDamageResolver $aoeDamageResolver = new AoEDamageResolver(),
        private readonly SelfBuffShieldResolver $selfBuffShieldResolver = new SelfBuffShieldResolver(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $context
     */
    public function resolve(array $actorUnit, array $targetUnits, array $skillState, array $context = []): array
    {
        $skillType = trim((string) ($skillState['skill_type'] ?? ''));

        if ($skillType === 'single_damage') {
            return $this->resolveSingleDamage($actorUnit, $targetUnits, $skillState, $context);
        }

        if ($skillType === 'multi_hit') {
            return $this->resolveMultiHit($actorUnit, $targetUnits, $skillState, $context);
        }

        if ($skillType === 'aoe') {
            return $this->resolveAoe($actorUnit, $targetUnits, $skillState, $context);
        }

        if ($skillType === 'self_buff' || $skillType === 'shield') {
            return $this->resolveSelfBuffOrShield($actorUnit, $targetUnits, $skillState);
        }

        return $this->failure('unsupported_skill_type');
    }

    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $context
     */
    private function resolveSingleDamage(array $actorUnit, array $targetUnits, array $skillState, array $context): array
    {
        $targetIndex = $this->resolvePrimaryTargetIndex($targetUnits, $context);
        if ($targetIndex === null) {
            return $this->failure('missing_primary_target');
        }

        $damageResult = $this->expandedDamageResolver->resolveSkillDamage(
            $actorUnit,
            $targetUnits[$targetIndex],
            $skillState,
            $context
        );
        if (! ($damageResult['ok'] ?? false)) {
            return $this->failure((string) ($damageResult['reason'] ?? 'single_damage_resolve_failed'));
        }

        $damageData = is_array($damageResult['data'] ?? null) ? $damageResult['data'] : [];
        $targetUnits[$targetIndex] = $this->applyDamageToTargetUnit($targetUnits[$targetIndex], $damageData);

        return $this->success([
            'actor_unit' => $actorUnit,
            'target_units' => $targetUnits,
            'entries' => [$this->buildDamageEntry($targetUnits[$targetIndex], $damageData)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $context
     */
    private function resolveMultiHit(array $actorUnit, array $targetUnits, array $skillState, array $context): array
    {
        $targetIndex = $this->resolvePrimaryTargetIndex($targetUnits, $context);
        if ($targetIndex === null) {
            return $this->failure('missing_primary_target');
        }

        $resolveResult = $this->multiHitDamageResolver->resolve(
            $actorUnit,
            $targetUnits[$targetIndex],
            $skillState,
            $context
        );
        if (! ($resolveResult['ok'] ?? false)) {
            return $this->failure((string) ($resolveResult['reason'] ?? 'multi_hit_resolve_failed'));
        }

        $resolveData = is_array($resolveResult['data'] ?? null) ? $resolveResult['data'] : [];
        if (is_array($resolveData['target_unit'] ?? null)) {
            $targetUnits[$targetIndex] = $resolveData['target_unit'];
        }

        return $this->success([
            'actor_unit' => $actorUnit,
            'target_units' => $targetUnits,
            'entries' => $this->annotateEntries(
                is_array($resolveData['hits'] ?? null) ? $resolveData['hits'] : [],
                'skill_cast'
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $context
     */
    private function resolveAoe(array $actorUnit, array $targetUnits, array $skillState, array $context): array
    {
        $resolveResult = $this->aoeDamageResolver->resolve($actorUnit, $targetUnits, $skillState, $context);
        if (! ($resolveResult['ok'] ?? false)) {
            return $this->failure((string) ($resolveResult['reason'] ?? 'aoe_resolve_failed'));
        }

        $resolveData = is_array($resolveResult['data'] ?? null) ? $resolveResult['data'] : [];

        return $this->success([
            'actor_unit' => $actorUnit,
            'target_units' => is_array($resolveData['target_units'] ?? null) ? $resolveData['target_units'] : $targetUnits,
            'entries' => $this->annotateEntries(
                is_array($resolveData['hits'] ?? null) ? $resolveData['hits'] : [],
                'skill_cast'
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $skillState
     */
    private function resolveSelfBuffOrShield(array $actorUnit, array $targetUnits, array $skillState): array
    {
        $resolveResult = $this->selfBuffShieldResolver->resolve($actorUnit, $skillState);
        if (! ($resolveResult['ok'] ?? false)) {
            return $this->failure((string) ($resolveResult['reason'] ?? 'self_buff_shield_resolve_failed'));
        }

        $resolveData = is_array($resolveResult['data'] ?? null) ? $resolveResult['data'] : [];

        return $this->success([
            'actor_unit' => is_array($resolveData['actor_unit'] ?? null) ? $resolveData['actor_unit'] : $actorUnit,
            'target_units' => $targetUnits,
            'entries' => $this->annotateEntries(
                is_array($resolveData['effects'] ?? null) ? $resolveData['effects'] : [],
                'effect_apply'
            ),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $context
     */
    private function resolvePrimaryTargetIndex(array $targetUnits, array $context): ?int
    {
        $primaryTargetIndex = $context['primary_target_index'] ?? null;
        if (! is_numeric($primaryTargetIndex)) {
            return null;
        }

        $targetIndex = (int) $primaryTargetIndex;

        return isset($targetUnits[$targetIndex]) && is_array($targetUnits[$targetIndex])
            ? $targetIndex
            : null;
    }

    /**
     * @param  array<string, mixed>  $targetUnit
     * @param  array<string, mixed>  $damageData
     * @return array<string, mixed>
     */
    private function buildDamageEntry(array $targetUnit, array $damageData): array
    {
        return [
            'target_unit_id' => trim((string) ($targetUnit['unit_id'] ?? '')),
            'raw_damage' => $damageData['raw_damage'] ?? 0,
            'hp_damage' => $damageData['hp_damage'] ?? 0,
            'shield_absorbed' => $damageData['shield_absorbed'] ?? 0,
            'is_critical' => (bool) ($damageData['is_critical'] ?? false),
        ];
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
                max(0.0, (float) ($targetUnit['current_hp'] ?? 0) - (float) ($damageData['hp_damage'] ?? 0))
            );
        }

        return $targetUnit;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function annotateEntries(array $entries, string $logAction): array
    {
        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entries[$index]['log_action'] = $logAction;
        }

        return array_values(array_filter(
            $entries,
            static fn (mixed $entry): bool => is_array($entry)
        ));
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
