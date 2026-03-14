<?php

namespace App\Services\Game\Battle;

class SkillCastResolver
{
    public function __construct(
        private readonly SkillCooldownResolver $skillCooldownResolver = new SkillCooldownResolver(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @param  array<int, array<string, mixed>>  $skillStates
     */
    public function resolvePlayerCast(array $playerUnit, array $enemyUnits, array $skillStates): array
    {
        if (($playerUnit['alive'] ?? false) !== true) {
            return $this->notCastable();
        }

        $skillState = $this->findFirstCastableSkillState($skillStates);
        if ($skillState === null) {
            return $this->notCastable();
        }

        $targetIndex = $this->findCurrentWaveTargetIndex($enemyUnits);
        if ($targetIndex === null) {
            return $this->notCastable();
        }

        $targetUnitId = trim((string) ($enemyUnits[$targetIndex]['unit_id'] ?? ''));
        if ($targetUnitId === '') {
            return $this->notCastable();
        }

        return $this->success([
            'can_cast' => true,
            'skill' => $skillState,
            'target_unit_id' => $targetUnitId,
            'target_enemy_index' => $targetIndex,
        ]);
    }

    /**
     * @param  array<string, mixed>  $enemyUnit
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $skillStates
     */
    public function resolveEnemyCast(array $enemyUnit, array $playerUnit, array $skillStates): array
    {
        if (($enemyUnit['alive'] ?? false) !== true || ($playerUnit['alive'] ?? false) !== true) {
            return $this->notCastable();
        }

        $skillState = $this->findFirstCastableSkillState($skillStates);
        if ($skillState === null) {
            return $this->notCastable();
        }

        $targetUnitId = trim((string) ($playerUnit['unit_id'] ?? ''));
        if ($targetUnitId === '') {
            return $this->notCastable();
        }

        return $this->success([
            'can_cast' => true,
            'skill' => $skillState,
            'target_unit_id' => $targetUnitId,
            'target_enemy_index' => null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $skillStates
     */
    private function findFirstCastableSkillState(array $skillStates): ?array
    {
        foreach ($skillStates as $skillState) {
            if (! is_array($skillState)) {
                continue;
            }

            $canCastResult = $this->skillCooldownResolver->canCast($skillState);
            if (($canCastResult['ok'] ?? false) !== true) {
                continue;
            }

            if (($canCastResult['data']['can_cast'] ?? false) === true) {
                return $skillState;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $enemyUnits
     */
    private function findCurrentWaveTargetIndex(array $enemyUnits): ?int
    {
        $currentWaveIndex = null;
        foreach ($enemyUnits as $enemyUnit) {
            if (($enemyUnit['alive'] ?? false) !== true) {
                continue;
            }

            $waveIndex = max(1, (int) ($enemyUnit['wave_index'] ?? 1));
            if ($currentWaveIndex === null || $waveIndex < $currentWaveIndex) {
                $currentWaveIndex = $waveIndex;
            }
        }

        if ($currentWaveIndex === null) {
            return null;
        }

        $selectedIndex = null;
        $selectedKey = null;

        foreach ($enemyUnits as $index => $enemyUnit) {
            if (($enemyUnit['alive'] ?? false) !== true) {
                continue;
            }

            if (max(1, (int) ($enemyUnit['wave_index'] ?? 1)) !== $currentWaveIndex) {
                continue;
            }

            $candidateKey = [(int) ($enemyUnit['unit_index'] ?? PHP_INT_MAX), (string) ($enemyUnit['unit_id'] ?? '')];
            if ($selectedKey === null || $candidateKey < $selectedKey) {
                $selectedKey = $candidateKey;
                $selectedIndex = $index;
            }
        }

        return $selectedIndex;
    }

    private function notCastable(): array
    {
        return $this->success([
            'can_cast' => false,
            'skill' => null,
            'target_unit_id' => null,
            'target_enemy_index' => null,
        ]);
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
