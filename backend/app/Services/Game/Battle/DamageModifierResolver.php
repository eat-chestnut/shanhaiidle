<?php

namespace App\Services\Game\Battle;

class DamageModifierResolver
{
    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     */
    public function resolveBasicAttackModifiers(array $attackerUnit, array $targetUnit): array
    {
        return $this->success([
            'attack_multiplier' => $this->normalizeNumber(
                $this->resolveMultiplier($attackerUnit, ['bonus_melee_atk'])
            ),
            'skill_multiplier' => 1,
            'boss_multiplier' => $this->normalizeNumber($this->resolveBossMultiplier($attackerUnit, $targetUnit)),
            'final_multiplier' => $this->normalizeNumber(
                $this->resolveMultiplier($attackerUnit, ['final_damage_bonus', 'bonus_final_damage'])
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     */
    public function resolveSkillDamageModifiers(array $attackerUnit, array $targetUnit): array
    {
        return $this->success([
            'attack_multiplier' => 1,
            'skill_multiplier' => $this->normalizeNumber(
                $this->resolveMultiplier($attackerUnit, ['bonus_skill_dmg'])
            ),
            'boss_multiplier' => $this->normalizeNumber($this->resolveBossMultiplier($attackerUnit, $targetUnit)),
            'final_multiplier' => $this->normalizeNumber(
                $this->resolveMultiplier($attackerUnit, ['final_damage_bonus', 'bonus_final_damage'])
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<int, string>  $keys
     */
    private function resolveMultiplier(array $attackerUnit, array $keys): float
    {
        $percent = $this->sumPercentBonus($this->resolveBonusRows($attackerUnit, $keys));

        return max(0.0, 1 + ($percent / 100));
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     */
    private function resolveBossMultiplier(array $attackerUnit, array $targetUnit): float
    {
        if (($targetUnit['is_boss'] ?? false) !== true) {
            return 1.0;
        }

        return $this->resolveMultiplier($attackerUnit, ['bonus_boss_dmg']);
    }

    /**
     * @param  array<string, mixed>  $unit
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function resolveBonusRows(array $unit, array $keys): array
    {
        $bonusStats = is_array($unit['bonus_stats'] ?? null) ? $unit['bonus_stats'] : [];
        $rows = [];

        foreach ($keys as $key) {
            foreach (is_array($bonusStats[$key] ?? null) ? $bonusStats[$key] : [] as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bonusRows
     */
    private function sumPercentBonus(array $bonusRows): float
    {
        $totalPercent = 0.0;

        foreach ($bonusRows as $bonusRow) {
            if (trim((string) ($bonusRow['value_type'] ?? 'percent')) !== 'percent') {
                continue;
            }

            $totalPercent += (float) ($bonusRow['value'] ?? 0);
        }

        return $totalPercent;
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
}
