<?php

namespace App\Services\Game\Inventory;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class RewardApplyTransactionService
{
    public function __construct(
        private readonly RewardGrantGuardService $rewardGrantGuardService,
        private readonly RewardGrantService $rewardGrantService,
        private readonly RewardGrantLogService $rewardGrantLogService,
    ) {
    }

    public function execute(string|int $playerId, string $battleId, array $rewardPayload): array
    {
        $safeBattleId = trim($battleId);

        $guardResult = $this->rewardGrantGuardService->check($playerId, $safeBattleId, $rewardPayload);
        if (! ($guardResult['ok'] ?? false) || ! ($guardResult['data']['allowed'] ?? false)) {
            return $this->failure(
                (string) ($guardResult['reason'] ?? 'reward grant rejected'),
                $safeBattleId,
            );
        }

        $rewardItems = is_array($rewardPayload['reward_items'] ?? null)
            ? $rewardPayload['reward_items']
            : [];
        $rewardSourceType = trim((string) ($rewardPayload['reward_source_type'] ?? 'battle_settlement'));
        $rewardSourceId = trim((string) ($rewardPayload['reward_source_id'] ?? $safeBattleId));
        $grantBatchId = trim((string) ($rewardPayload['grant_batch_id'] ?? ''));

        try {
            return DB::transaction(function () use (
                $playerId,
                $safeBattleId,
                $rewardItems,
                $rewardSourceType,
                $rewardSourceId,
                $grantBatchId,
            ): array {
                $grantResult = $this->rewardGrantService->execute($playerId, $rewardItems);
                if (! ($grantResult['ok'] ?? false)) {
                    throw new RuntimeException((string) ($grantResult['reason'] ?? 'reward grant failed'));
                }

                $grantedItems = is_array($grantResult['data']['granted_items'] ?? null)
                    ? $grantResult['data']['granted_items']
                    : [];

                $logResult = $this->rewardGrantLogService->record(
                    $playerId,
                    $safeBattleId,
                    $grantedItems,
                    $rewardSourceType,
                    $rewardSourceId,
                    $grantBatchId,
                );

                if (! ($logResult['ok'] ?? false)) {
                    throw new RuntimeException((string) ($logResult['reason'] ?? 'reward log record failed'));
                }

                return $this->success($safeBattleId, $grantedItems);
            });
        } catch (RuntimeException $exception) {
            return $this->failure($exception->getMessage(), $safeBattleId);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $grantedItems
     */
    private function success(string $battleId, array $grantedItems): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => [
                'battle_id' => $battleId,
                'granted' => true,
                'granted_items' => $grantedItems,
            ],
        ];
    }

    private function failure(string $reason, string $battleId): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => [
                'battle_id' => $battleId,
                'granted' => false,
                'granted_items' => [],
            ],
        ];
    }
}
