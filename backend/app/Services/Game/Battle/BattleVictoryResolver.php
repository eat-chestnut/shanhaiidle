<?php

namespace App\Services\Game\Battle;

class BattleVictoryResolver
{
    public function resolve(array $runtimeState): array
    {
        $playerAlive = (bool) ($runtimeState['player_unit']['alive'] ?? false);
        $enemyUnits = is_array($runtimeState['enemy_units'] ?? null) ? $runtimeState['enemy_units'] : [];

        if (! $playerAlive) {
            return $this->success([
                'battle_result' => 'defeat',
            ]);
        }

        foreach ($enemyUnits as $enemyUnit) {
            if (($enemyUnit['alive'] ?? false) === true) {
                return $this->success([
                    'battle_result' => 'ongoing',
                ]);
            }
        }

        return $this->success([
            'battle_result' => 'victory',
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
