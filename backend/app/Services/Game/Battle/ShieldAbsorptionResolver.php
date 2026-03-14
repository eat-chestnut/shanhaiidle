<?php

namespace App\Services\Game\Battle;

class ShieldAbsorptionResolver
{
    /**
     * @param  array<string, mixed>  $targetUnit
     */
    public function resolve(array $targetUnit, int|float $finalDamage): array
    {
        $incomingDamage = max(0.0, (float) $finalDamage);
        $currentShield = max(0.0, (float) ($targetUnit['shield'] ?? 0));
        $currentHp = max(0.0, (float) ($targetUnit['current_hp'] ?? 0));

        $absorbedByShield = min($currentShield, $incomingDamage);
        $remainingDamage = max(0.0, $incomingDamage - $absorbedByShield);
        $hpDamage = min($currentHp, $remainingDamage);
        $remainingShield = max(0.0, $currentShield - $absorbedByShield);
        $remainingHp = max(0.0, $currentHp - $hpDamage);

        return $this->success([
            'absorbed_by_shield' => $this->normalizeNumber($absorbedByShield),
            'hp_damage' => $this->normalizeNumber($hpDamage),
            'remaining_shield' => $this->normalizeNumber($remainingShield),
            'remaining_hp' => $this->normalizeNumber($remainingHp),
        ]);
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
