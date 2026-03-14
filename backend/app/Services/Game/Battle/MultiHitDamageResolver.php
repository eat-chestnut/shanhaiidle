<?php

namespace App\Services\Game\Battle;

class MultiHitDamageResolver
{
    public function __construct(
        private readonly ExpandedDamageResolver $expandedDamageResolver = new ExpandedDamageResolver(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $attackContext
     */
    public function resolve(array $attackerUnit, array $targetUnit, array $skillState, array $attackContext = []): array
    {
        if (trim((string) ($skillState['skill_type'] ?? '')) !== 'multi_hit') {
            return $this->failure('unsupported_skill_type');
        }

        $multiHitCount = max(0, (int) ($skillState['multi_hit_count'] ?? 0));
        if ($multiHitCount <= 0) {
            return $this->failure('invalid_multi_hit_count');
        }

        $hitDamageRatios = $this->resolveHitDamageRatios($skillState, $multiHitCount);
        if ($hitDamageRatios === null) {
            return $this->failure('invalid_hit_damage_ratios');
        }

        $hits = [];
        foreach ($hitDamageRatios as $hitIndex => $damageRatio) {
            $damageResult = $this->expandedDamageResolver->resolveSkillDamage(
                $attackerUnit,
                $targetUnit,
                [
                    'skill_id' => $skillState['skill_id'] ?? null,
                    'skill_type' => 'single_damage',
                    'damage_ratio' => $damageRatio,
                ],
                $this->buildPerHitAttackContext($attackContext, $hitIndex + 1)
            );
            if (! ($damageResult['ok'] ?? false)) {
                return $this->failure((string) ($damageResult['reason'] ?? 'multi_hit_damage_resolve_failed'));
            }

            $damageData = is_array($damageResult['data'] ?? null) ? $damageResult['data'] : [];
            $targetUnit = $this->applyDamageToTargetUnit($targetUnit, $damageData);

            $hits[] = [
                'target_unit_id' => trim((string) ($targetUnit['unit_id'] ?? '')),
                'raw_damage' => $damageData['raw_damage'] ?? 0,
                'hp_damage' => $damageData['hp_damage'] ?? 0,
                'shield_absorbed' => $damageData['shield_absorbed'] ?? 0,
                'is_critical' => (bool) ($damageData['is_critical'] ?? false),
                'hit_index' => $hitIndex + 1,
            ];
        }

        return $this->success([
            'target_unit' => $targetUnit,
            'hits' => $hits,
        ]);
    }

    /**
     * @param  array<string, mixed>  $skillState
     * @return array<int, float>|null
     */
    private function resolveHitDamageRatios(array $skillState, int $multiHitCount): ?array
    {
        $rawRatios = is_array($skillState['hit_damage_ratios'] ?? null)
            ? $skillState['hit_damage_ratios']
            : [];

        $hitDamageRatios = [];
        foreach ($rawRatios as $rawRatio) {
            if (! is_numeric($rawRatio) || (float) $rawRatio <= 0) {
                return null;
            }

            $hitDamageRatios[] = (float) $rawRatio;
        }

        if ($hitDamageRatios !== []) {
            if (count($hitDamageRatios) !== $multiHitCount) {
                return null;
            }

            return $hitDamageRatios;
        }

        $damageRatio = (float) ($skillState['damage_ratio'] ?? 0);
        if ($damageRatio <= 0) {
            return null;
        }

        return array_fill(0, $multiHitCount, $damageRatio);
    }

    /**
     * @param  array<string, mixed>  $attackContext
     * @return array<string, mixed>
     */
    private function buildPerHitAttackContext(array $attackContext, int $hitIndex): array
    {
        $perHitAttackContext = $attackContext;
        $perHitAttackContext['hit_index'] = $hitIndex;

        $forcedRolls = is_array($attackContext['forced_rolls'] ?? null) ? array_values($attackContext['forced_rolls']) : [];
        if (is_numeric($forcedRolls[$hitIndex - 1] ?? null)) {
            $perHitAttackContext['forced_roll'] = (float) $forcedRolls[$hitIndex - 1];
        }

        return $perHitAttackContext;
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
