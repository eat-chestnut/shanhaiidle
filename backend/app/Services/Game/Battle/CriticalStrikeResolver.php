<?php

namespace App\Services\Game\Battle;

class CriticalStrikeResolver
{
    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $attackContext
     */
    public function resolve(array $attackerUnit, array $attackContext = []): array
    {
        $forcedRoll = $attackContext['forced_roll'] ?? null;
        if (is_numeric($forcedRoll)) {
            return $this->resolveWithForcedRoll($attackerUnit, $attackContext, (float) $forcedRoll);
        }

        return $this->resolveWithForcedRoll(
            $attackerUnit,
            $attackContext,
            random_int(0, 1000000) / 1000000
        );
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $attackContext
     */
    public function resolveWithForcedRoll(array $attackerUnit, array $attackContext, float $rollValue): array
    {
        $criticalRate = $this->resolveCriticalRate($attackerUnit);
        $criticalDamagePercent = $this->sumPercentBonus(
            $this->resolveBonusRows($attackerUnit, ['bonus_crit_dmg'])
        );
        $isCritical = $this->normalizeRoll($rollValue) <= $criticalRate;
        $criticalMultiplier = $isCritical
            ? 1 + ($criticalDamagePercent / 100)
            : 1.0;

        return $this->success([
            'is_critical' => $isCritical,
            'critical_multiplier' => $this->normalizeNumber($criticalMultiplier),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     */
    private function resolveCriticalRate(array $attackerUnit): float
    {
        $criticalRatePercent = $this->sumPercentBonus(
            $this->resolveBonusRows($attackerUnit, ['bonus_crit_rate'])
        );

        return min(1.0, max(0.0, $criticalRatePercent / 100));
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

    private function normalizeRoll(float $rollValue): float
    {
        return min(1.0, max(0.0, $rollValue));
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
