<?php

namespace App\Services\Game\Battle;

class BasicDamageResolver
{
    public function __construct(
        private readonly ExpandedDamageResolver $expandedDamageResolver = new ExpandedDamageResolver(),
    ) {
    }

    public function resolvePlayerToEnemy(array $playerUnit, array $enemyUnit): array
    {
        return $this->resolve($playerUnit, $enemyUnit);
    }

    public function resolveEnemyToPlayer(array $enemyUnit, array $playerUnit): array
    {
        return $this->resolve($enemyUnit, $playerUnit);
    }

    private function resolve(array $attackerUnit, array $targetUnit): array
    {
        $result = $this->expandedDamageResolver->resolveBasicAttack($attackerUnit, $targetUnit);
        if (! ($result['ok'] ?? false)) {
            return $this->failure((string) ($result['reason'] ?? 'expanded_basic_attack_resolve_failed'));
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
