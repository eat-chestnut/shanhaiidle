<?php

namespace App\Services\Game\Battle;

class BasicDamageResolver
{
    public function resolvePlayerToEnemy(array $playerUnit, array $enemyUnit): array
    {
        $baseAttack = (int) ($playerUnit['stats']['MELEE_ATK'] ?? 0);
        $enemyDef = (int) ($enemyUnit['stats']['DEF'] ?? 0);
        $effectiveAttack = $this->applyPercentBonuses(
            $baseAttack,
            $playerUnit['bonus_stats']['bonus_melee_atk'] ?? []
        );

        $damage = max(1, $effectiveAttack - $enemyDef);

        if (($enemyUnit['is_boss'] ?? false) === true) {
            $damage = $this->applyPercentBonuses($damage, $playerUnit['bonus_stats']['bonus_boss_dmg'] ?? []);
        }

        return $this->success([
            'damage' => max(1, $damage),
        ]);
    }

    public function resolveEnemyToPlayer(array $enemyUnit, array $playerUnit): array
    {
        $enemyAttack = (int) ($enemyUnit['stats']['ATK'] ?? 0);
        $playerDef = (int) ($playerUnit['stats']['DEF'] ?? 0);

        return $this->success([
            'damage' => max(1, $enemyAttack - $playerDef),
        ]);
    }

    private function applyPercentBonuses(int $baseValue, mixed $bonusRows): int
    {
        $value = $baseValue;

        foreach (is_array($bonusRows) ? $bonusRows : [] as $bonusRow) {
            if (! is_array($bonusRow)) {
                continue;
            }

            $bonusValue = (float) ($bonusRow['value'] ?? 0);
            $valueType = trim((string) ($bonusRow['value_type'] ?? 'percent'));

            if ($valueType === 'percent') {
                $value += $baseValue * ($bonusValue / 100);

                continue;
            }

            if ($valueType === 'flat') {
                $value += $bonusValue;
            }
        }

        return max(0, (int) floor($value));
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
