<?php

namespace App\Services\Game\Battle;

class SkillDamageResolver
{
    public function __construct(
        private readonly ExpandedDamageResolver $expandedDamageResolver = new ExpandedDamageResolver(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<string, mixed>  $enemyUnit
     * @param  array<string, mixed>  $skillState
     */
    public function resolvePlayerSkillDamage(array $playerUnit, array $enemyUnit, array $skillState): array
    {
        return $this->resolve($playerUnit, $enemyUnit, $skillState);
    }

    /**
     * @param  array<string, mixed>  $enemyUnit
     * @param  array<string, mixed>  $playerUnit
     * @param  array<string, mixed>  $skillState
     */
    public function resolveEnemySkillDamage(array $enemyUnit, array $playerUnit, array $skillState): array
    {
        return $this->resolve($enemyUnit, $playerUnit, $skillState);
    }

    /**
     * @param  array<string, mixed>  $attackerUnit
     * @param  array<string, mixed>  $targetUnit
     * @param  array<string, mixed>  $skillState
     */
    private function resolve(array $attackerUnit, array $targetUnit, array $skillState): array
    {
        $result = $this->expandedDamageResolver->resolveSkillDamage($attackerUnit, $targetUnit, $skillState);
        if (! ($result['ok'] ?? false)) {
            return $this->failure((string) ($result['reason'] ?? 'expanded_skill_damage_resolve_failed'));
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $data['damage'] = $data['raw_damage'] ?? 0;

        return $this->success($data);
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
