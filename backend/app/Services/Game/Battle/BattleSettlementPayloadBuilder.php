<?php

namespace App\Services\Game\Battle;

use App\Models\BattleResult;

class BattleSettlementPayloadBuilder
{
    public function build(BattleResult|array $battleResultRecord, array $rewardResult): array
    {
        $battleResult = $battleResultRecord instanceof BattleResult
            ? (string) $battleResultRecord->battle_result
            : trim((string) ($battleResultRecord['battle_result'] ?? ''));

        return [
            'ok' => true,
            'reason' => null,
            'data' => [
                'battle_result' => $battleResult,
                'reward_items' => array_values($rewardResult['reward_items'] ?? []),
                'first_clear_granted' => (bool) ($rewardResult['first_clear_granted'] ?? false),
                'next_unlocks' => array_values($rewardResult['next_unlocks'] ?? []),
                'debug_reward_sources' => array_values($rewardResult['debug_reward_sources'] ?? []),
            ],
        ];
    }
}
