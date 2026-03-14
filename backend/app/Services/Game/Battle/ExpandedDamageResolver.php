<?php

namespace App\Services\Game\Battle;

class ExpandedDamageResolver
{
    public function __construct(
        private readonly CriticalStrikeResolver $criticalStrikeResolver = new CriticalStrikeResolver(),
        private readonly DamageModifierResolver $damageModifierResolver = new DamageModifierResolver(),
        private readonly ShieldAbsorptionResolver $shieldAbsorptionResolver = new ShieldAbsorptionResolver(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     * @param  array<string, mixed>  $attackContext
     */
    public function resolveBasicAttack(array $attackerUnit, array $targetUnit, array $attackContext = []): array
    {
        $modifierResult = $this->damageModifierResolver->resolveBasicAttackModifiers($attackerUnit, $targetUnit);
        if (! ($modifierResult['ok'] ?? false)) {
            return $this->failure((string) ($modifierResult['reason'] ?? 'basic_attack_modifier_resolve_failed'));
        }

        return $this->resolveExpandedDamage(
            $attackerUnit,
            $targetUnit,
            $attackContext,
            $this->resolveAttackStat($attackerUnit),
            is_array($modifierResult['data'] ?? null) ? $modifierResult['data'] : []
        );
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $attackContext
     */
    public function resolveSkillDamage(array $attackerUnit, array $targetUnit, array $skillState, array $attackContext = []): array
    {
        if (trim((string) ($skillState['skill_type'] ?? '')) !== 'single_damage') {
            return $this->failure('unsupported_skill_type');
        }

        $damageRatio = (float) ($skillState['damage_ratio'] ?? 0);
        if ($damageRatio <= 0) {
            return $this->failure('invalid_damage_ratio');
        }

        $modifierResult = $this->damageModifierResolver->resolveSkillDamageModifiers($attackerUnit, $targetUnit);
        if (! ($modifierResult['ok'] ?? false)) {
            return $this->failure((string) ($modifierResult['reason'] ?? 'skill_damage_modifier_resolve_failed'));
        }

        return $this->resolveExpandedDamage(
            $attackerUnit,
            $targetUnit,
            $attackContext,
            $this->resolveAttackStat($attackerUnit) * $damageRatio,
            is_array($modifierResult['data'] ?? null) ? $modifierResult['data'] : []
        );
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     * @param  array<string, mixed>  $attackContext
     * @param  array<string, mixed>  $modifierData
     */
    private function resolveExpandedDamage(
        array $attackerUnit,
        array $targetUnit,
        array $attackContext,
        float $baseDamage,
        array $modifierData,
    ): array {
        $criticalResult = $this->criticalStrikeResolver->resolve($attackerUnit, $attackContext);
        if (! ($criticalResult['ok'] ?? false)) {
            return $this->failure((string) ($criticalResult['reason'] ?? 'critical_strike_resolve_failed'));
        }

        $criticalData = is_array($criticalResult['data'] ?? null) ? $criticalResult['data'] : [];
        $attackMultiplier = (float) ($modifierData['attack_multiplier'] ?? 1);
        $skillMultiplier = (float) ($modifierData['skill_multiplier'] ?? 1);
        $bossMultiplier = (float) ($modifierData['boss_multiplier'] ?? 1);
        $finalMultiplier = (float) ($modifierData['final_multiplier'] ?? 1);
        $criticalMultiplier = (float) ($criticalData['critical_multiplier'] ?? 1);
        $preDefDamage = $baseDamage * $attackMultiplier * $skillMultiplier * $bossMultiplier * $criticalMultiplier;
        $postDefDamage = max(1.0, $preDefDamage - $this->resolveTargetDef($targetUnit));
        $finalDamage = $postDefDamage * $finalMultiplier;

        $shieldResult = $this->shieldAbsorptionResolver->resolve($targetUnit, $finalDamage);
        if (! ($shieldResult['ok'] ?? false)) {
            return $this->failure((string) ($shieldResult['reason'] ?? 'shield_absorption_resolve_failed'));
        }

        $shieldData = is_array($shieldResult['data'] ?? null) ? $shieldResult['data'] : [];

        return $this->success([
            'raw_damage' => $this->normalizeNumber($finalDamage),
            'is_critical' => (bool) ($criticalData['is_critical'] ?? false),
            'critical_multiplier' => $this->normalizeNumber($criticalMultiplier),
            'shield_absorbed' => $shieldData['absorbed_by_shield'] ?? 0,
            'hp_damage' => $shieldData['hp_damage'] ?? 0,
            'remaining_shield' => $shieldData['remaining_shield'] ?? 0,
            'remaining_hp' => $shieldData['remaining_hp'] ?? $this->normalizeNumber(
                max(0.0, (float) ($targetUnit['current_hp'] ?? 0) - (float) ($shieldData['hp_damage'] ?? 0))
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     */
    private function resolveAttackStat(array $attackerUnit): float
    {
        $stats = is_array($attackerUnit['stats'] ?? null) ? $attackerUnit['stats'] : $attackerUnit;

        if (array_key_exists('MELEE_ATK', $stats)) {
            return max(0.0, (float) $stats['MELEE_ATK']);
        }

        if (array_key_exists('ATK', $stats)) {
            return max(0.0, (float) $stats['ATK']);
        }

        if (array_key_exists('RANGED_ATK', $stats)) {
            return max(0.0, (float) $stats['RANGED_ATK']);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $targetUnit
     */
    private function resolveTargetDef(array $targetUnit): float
    {
        $stats = is_array($targetUnit['stats'] ?? null) ? $targetUnit['stats'] : $targetUnit;

        return max(0.0, (float) ($stats['DEF'] ?? 0));
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
