<?php

namespace App\Services\Game\Battle;

use App\Models\BattleResult;
use App\Models\PlayerMainStageFirstClearClaim;

class BattleSettlementGuardService
{
    public function check(string|int $playerId, string $battleId, array $battleContext): array
    {
        $safePlayerId = trim((string) $playerId);
        $safeBattleId = trim($battleId);

        if ($safePlayerId === '' || $safeBattleId === '') {
            return $this->failure('invalid_battle_context');
        }

        $existing = BattleResult::query()->where('battle_id', $safeBattleId)->first();
        if ($existing instanceof BattleResult) {
            return $this->failure(
                $existing->is_settled ? 'battle already settled' : 'duplicate settlement risk: battle result already persisted',
                [
                    'allowed' => false,
                    'first_clear_already_claimed' => false,
                    'duplicate_settlement_risk' => true,
                ],
            );
        }

        $firstClearAlreadyClaimed = $this->isMainStageFirstClearClaimed($safePlayerId, $battleContext);

        return $this->success([
            'allowed' => true,
            'first_clear_already_claimed' => $firstClearAlreadyClaimed,
            'duplicate_settlement_risk' => false,
        ]);
    }

    private function isMainStageFirstClearClaimed(string $playerId, array $battleContext): bool
    {
        if (trim((string) ($battleContext['battle_type'] ?? '')) !== 'main_stage') {
            return false;
        }

        $difficultyId = trim((string) ($battleContext['difficulty_id'] ?? ''));
        if ($difficultyId === '') {
            return false;
        }

        return PlayerMainStageFirstClearClaim::query()
            ->where('player_id', $playerId)
            ->where('difficulty_id', $difficultyId)
            ->exists();
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    private function failure(string $reason, array $data): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => $data,
        ];
    }
}
