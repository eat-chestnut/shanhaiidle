<?php

namespace App\Services\Game\Battle;

class AoEDamageResolver
{
    public function __construct(
        private readonly ExpandedDamageResolver $expandedDamageResolver = new ExpandedDamageResolver(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $attackContext
     */
    public function resolve(array $attackerUnit, array $targetUnits, array $skillState, array $attackContext = []): array
    {
        if (trim((string) ($skillState['skill_type'] ?? '')) !== 'aoe') {
            return $this->failure('unsupported_skill_type');
        }

        $damageRatio = (float) ($skillState['damage_ratio'] ?? 0);
        if ($damageRatio <= 0) {
            return $this->failure('invalid_damage_ratio');
        }

        $targetIndexes = $this->resolveTargetIndexes($targetUnits, $attackContext);
        if ($targetIndexes === []) {
            return $this->failure('no_aoe_targets');
        }

        $hits = [];
        foreach ($targetIndexes as $position => $targetIndex) {
            $damageResult = $this->expandedDamageResolver->resolveSkillDamage(
                $attackerUnit,
                $targetUnits[$targetIndex],
                [
                    'skill_id' => $skillState['skill_id'] ?? null,
                    'skill_type' => 'single_damage',
                    'damage_ratio' => $damageRatio,
                ],
                $this->buildPerTargetAttackContext($attackContext, $position)
            );
            if (! ($damageResult['ok'] ?? false)) {
                return $this->failure((string) ($damageResult['reason'] ?? 'aoe_damage_resolve_failed'));
            }

            $damageData = is_array($damageResult['data'] ?? null) ? $damageResult['data'] : [];
            $targetUnits[$targetIndex] = $this->applyDamageToTargetUnit($targetUnits[$targetIndex], $damageData);

            $hits[] = [
                'target_unit_id' => trim((string) ($targetUnits[$targetIndex]['unit_id'] ?? '')),
                'raw_damage' => $damageData['raw_damage'] ?? 0,
                'hp_damage' => $damageData['hp_damage'] ?? 0,
                'shield_absorbed' => $damageData['shield_absorbed'] ?? 0,
                'is_critical' => (bool) ($damageData['is_critical'] ?? false),
            ];
        }

        return $this->success([
            'target_units' => $targetUnits,
            'hits' => $hits,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $targetUnits
     * @param  array<string, mixed>  $attackContext
     * @return array<int, int>
     */
    private function resolveTargetIndexes(array $targetUnits, array $attackContext): array
    {
        $rawTargetIndexes = is_array($attackContext['target_indexes'] ?? null)
            ? $attackContext['target_indexes']
            : array_keys($targetUnits);

        $targetIndexes = [];
        foreach ($rawTargetIndexes as $rawTargetIndex) {
            if (! is_numeric($rawTargetIndex)) {
                continue;
            }

            $targetIndex = (int) $rawTargetIndex;
            if (! isset($targetUnits[$targetIndex]) || ! is_array($targetUnits[$targetIndex])) {
                continue;
            }

            if (($targetUnits[$targetIndex]['alive'] ?? true) !== true) {
                continue;
            }

            $targetIndexes[] = $targetIndex;
        }

        return array_values(array_unique($targetIndexes));
    }

    /**
     * @param  array<string, mixed>  $attackContext
     * @return array<string, mixed>
     */
    private function buildPerTargetAttackContext(array $attackContext, int $position): array
    {
        $perTargetAttackContext = $attackContext;

        $forcedRolls = is_array($attackContext['forced_rolls'] ?? null) ? array_values($attackContext['forced_rolls']) : [];
        if (is_numeric($forcedRolls[$position] ?? null)) {
            $perTargetAttackContext['forced_roll'] = (float) $forcedRolls[$position];
        }

        return $perTargetAttackContext;
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
