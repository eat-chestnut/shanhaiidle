<?php

namespace App\Services\Game\Battle;

class SkillDamageResolver
{
    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<string, mixed>  $enemyUnit
     * @param  array<string, mixed>  $skillState
     */
    public function resolvePlayerSkillDamage(array $playerUnit, array $enemyUnit, array $skillState): array
    {
        if (trim((string) ($skillState['skill_type'] ?? '')) !== 'single_damage') {
            return $this->failure('unsupported_skill_type');
        }

        $playerAttack = (int) ($playerUnit['stats']['MELEE_ATK'] ?? 0);
        $damageRatio = (float) ($skillState['damage_ratio'] ?? 0);
        $targetDef = (int) ($enemyUnit['stats']['DEF'] ?? 0);
        $bonusSkillDmgPercent = $this->sumPercentBonus($playerUnit['bonus_stats']['bonus_skill_dmg'] ?? []);

        $baseSkillDamage = $playerAttack * $damageRatio;
        $skillBonusMultiplier = 1 + ($bonusSkillDmgPercent / 100);
        $finalDamage = max(1, (int) floor(($baseSkillDamage * $skillBonusMultiplier) - $targetDef));

        return $this->success([
            'damage' => $finalDamage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $enemyUnit
     * @param  array<string, mixed>  $playerUnit
     * @param  array<string, mixed>  $skillState
     */
    public function resolveEnemySkillDamage(array $enemyUnit, array $playerUnit, array $skillState): array
    {
        if (trim((string) ($skillState['skill_type'] ?? '')) !== 'single_damage') {
            return $this->failure('unsupported_skill_type');
        }

        $enemyAttack = (int) ($enemyUnit['stats']['ATK'] ?? 0);
        $damageRatio = (float) ($skillState['damage_ratio'] ?? 0);
        $targetDef = (int) ($playerUnit['stats']['DEF'] ?? 0);

        $baseSkillDamage = $enemyAttack * $damageRatio;
        $finalDamage = max(1, (int) floor($baseSkillDamage - $targetDef));

        return $this->success([
            'damage' => $finalDamage,
        ]);
    }

    private function sumPercentBonus(mixed $bonusRows): float
    {
        $totalPercent = 0.0;

        foreach (is_array($bonusRows) ? $bonusRows : [] as $bonusRow) {
            if (! is_array($bonusRow)) {
                continue;
            }

            if (trim((string) ($bonusRow['value_type'] ?? 'percent')) !== 'percent') {
                continue;
            }

            $totalPercent += (float) ($bonusRow['value'] ?? 0);
        }

        return $totalPercent;
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
