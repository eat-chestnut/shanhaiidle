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

        $currentWaveTargetIndexes = $this->findCurrentWaveTargetIndexes($enemyUnits);

        foreach ($skillStates as $skillState) {
            if (! is_array($skillState)) {
                continue;
            }

            $canCastResult = $this->skillCooldownResolver->canCast($skillState);
            if (($canCastResult['ok'] ?? false) !== true || ($canCastResult['data']['can_cast'] ?? false) !== true) {
                continue;
            }

            $castData = $this->buildPlayerCastData($playerUnit, $enemyUnits, $skillState, $currentWaveTargetIndexes);
            if ($castData !== null) {
                return $this->success($castData);
            }
        }

        return $this->notCastable();
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

        foreach ($skillStates as $skillState) {
            if (! is_array($skillState)) {
                continue;
            }

            $canCastResult = $this->skillCooldownResolver->canCast($skillState);
            if (($canCastResult['ok'] ?? false) !== true || ($canCastResult['data']['can_cast'] ?? false) !== true) {
                continue;
            }

            $castData = $this->buildEnemyCastData($enemyUnit, $playerUnit, $skillState);
            if ($castData !== null) {
                return $this->success($castData);
            }
        }

        return $this->notCastable();
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @param  array<string, mixed>  $skillState
     * @param  array<int, int>  $currentWaveTargetIndexes
     */
    private function buildPlayerCastData(
        array $playerUnit,
        array $enemyUnits,
        array $skillState,
        array $currentWaveTargetIndexes,
    ): ?array
    {
        $skillType = trim((string) ($skillState['skill_type'] ?? ''));
        $actorUnitId = trim((string) ($playerUnit['unit_id'] ?? ''));

        if ($actorUnitId === '') {
            return null;
        }

        if ($this->isSelfCastSkillType($skillType)) {
            return [
                'can_cast' => true,
                'skill' => $skillState,
                'target_unit_id' => $actorUnitId,
                'target_enemy_index' => null,
                'target_enemy_indexes' => [],
            ];
        }

        if ($currentWaveTargetIndexes === []) {
            return null;
        }

        $targetIndex = $currentWaveTargetIndexes[0];
        $targetUnitId = trim((string) ($enemyUnits[$targetIndex]['unit_id'] ?? ''));
        if ($targetUnitId === '') {
            return null;
        }

        return [
            'can_cast' => true,
            'skill' => $skillState,
            'target_unit_id' => $targetUnitId,
            'target_enemy_index' => $targetIndex,
            'target_enemy_indexes' => $skillType === 'aoe' ? $currentWaveTargetIndexes : [$targetIndex],
        ];
    }

    /**
     * @param  array<string, mixed>  $enemyUnit
     * @param  array<string, mixed>  $playerUnit
     * @param  array<string, mixed>  $skillState
     */
    private function buildEnemyCastData(array $enemyUnit, array $playerUnit, array $skillState): ?array
    {
        $skillType = trim((string) ($skillState['skill_type'] ?? ''));
        $actorUnitId = trim((string) ($enemyUnit['unit_id'] ?? ''));

        if ($actorUnitId === '') {
            return null;
        }

        if ($this->isSelfCastSkillType($skillType)) {
            return [
                'can_cast' => true,
                'skill' => $skillState,
                'target_unit_id' => $actorUnitId,
                'target_enemy_index' => null,
                'target_enemy_indexes' => [],
            ];
        }

        $targetUnitId = trim((string) ($playerUnit['unit_id'] ?? ''));
        if ($targetUnitId === '') {
            return null;
        }

        return [
            'can_cast' => true,
            'skill' => $skillState,
            'target_unit_id' => $targetUnitId,
            'target_enemy_index' => null,
            'target_enemy_indexes' => [0],
        ];
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

    /**
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @return array<int, int>
     */
    private function findCurrentWaveTargetIndexes(array $enemyUnits): array
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
            return [];
        }

        $orderedTargets = [];
        foreach ($enemyUnits as $index => $enemyUnit) {
            if (($enemyUnit['alive'] ?? false) !== true) {
                continue;
            }

            if (max(1, (int) ($enemyUnit['wave_index'] ?? 1)) !== $currentWaveIndex) {
                continue;
            }

            $orderedTargets[] = [
                'index' => $index,
                'sort_key' => [(int) ($enemyUnit['unit_index'] ?? PHP_INT_MAX), (string) ($enemyUnit['unit_id'] ?? '')],
            ];
        }

        usort($orderedTargets, static fn (array $left, array $right): int => $left['sort_key'] <=> $right['sort_key']);

        return array_values(array_map(
            static fn (array $row): int => (int) $row['index'],
            $orderedTargets
        ));
    }

    private function isSelfCastSkillType(string $skillType): bool
    {
        return in_array($skillType, ['self_buff', 'shield'], true);
    }

    private function notCastable(): array
    {
        return $this->success([
            'can_cast' => false,
            'skill' => null,
            'target_unit_id' => null,
            'target_enemy_index' => null,
            'target_enemy_indexes' => [],
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
