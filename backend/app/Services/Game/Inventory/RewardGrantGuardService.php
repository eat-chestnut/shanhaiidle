<?php

namespace App\Services\Game\Inventory;

use App\Models\PlayerMainStageFirstClearClaim;
use App\Models\RewardGrantLog;

class RewardGrantGuardService
{
    public function check(string|int $playerId, string $battleId, array $rewardPayload): array
    {
        $safePlayerId = trim((string) $playerId);
        $safeBattleId = trim($battleId);

        if ($safePlayerId === '' || $safeBattleId === '') {
            return $this->failure('invalid_reward_context');
        }

        $grantBatchId = trim((string) ($rewardPayload['grant_batch_id'] ?? ''));
        if ($grantBatchId === '') {
            return $this->failure('invalid reward payload: grant_batch_id is required');
        }

        if (RewardGrantLog::query()->where('battle_id', $safeBattleId)->exists()) {
            return $this->failure(sprintf('reward already granted for battle_id %s', $safeBattleId));
        }

        if (RewardGrantLog::query()->where('grant_batch_id', $grantBatchId)->exists()) {
            return $this->failure(sprintf('reward already granted for grant_batch_id %s', $grantBatchId));
        }

        foreach ($this->extractMainStageFirstClearSourceIds($rewardPayload) as $sourceId) {
            $alreadyGranted = RewardGrantLog::query()
                ->where('player_id', $safePlayerId)
                ->where('reward_source_type', 'main_stage_first_clear')
                ->where('reward_source_id', $sourceId)
                ->exists();

            if ($alreadyGranted) {
                return $this->failure(sprintf('first clear reward already granted for source %s', $sourceId));
            }
        }

        $firstClearClaim = is_array($rewardPayload['first_clear_claim'] ?? null)
            ? $rewardPayload['first_clear_claim']
            : [];
        $difficultyId = trim((string) ($firstClearClaim['difficulty_id'] ?? ''));

        if ($difficultyId !== '') {
            $alreadyClaimed = PlayerMainStageFirstClearClaim::query()
                ->where('player_id', $safePlayerId)
                ->where('difficulty_id', $difficultyId)
                ->exists();

            if ($alreadyClaimed) {
                return $this->failure(sprintf('first clear reward already granted for difficulty_id %s', $difficultyId));
            }
        }

        return $this->success([
            'allowed' => true,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function extractMainStageFirstClearSourceIds(array $rewardPayload): array
    {
        $sourceIds = [];
        $globalSourceType = trim((string) ($rewardPayload['reward_source_type'] ?? ''));
        $globalSourceId = trim((string) ($rewardPayload['reward_source_id'] ?? ''));

        if ($globalSourceType === 'main_stage_first_clear' && $globalSourceId !== '') {
            $sourceIds[] = $globalSourceId;
        }

        $rewardItems = is_array($rewardPayload['reward_items'] ?? null)
            ? $rewardPayload['reward_items']
            : [];

        foreach ($rewardItems as $rewardItem) {
            if (! is_array($rewardItem)) {
                continue;
            }

            $itemSourceType = trim((string) ($rewardItem['reward_source_type'] ?? ''));
            $itemSourceId = trim((string) ($rewardItem['reward_source_id'] ?? ''));

            if ($itemSourceType === 'main_stage_first_clear' && $itemSourceId !== '') {
                $sourceIds[] = $itemSourceId;
            }
        }

        $sourceIds = array_values(array_unique($sourceIds));
        sort($sourceIds);

        return $sourceIds;
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
            'data' => [
                'allowed' => false,
            ],
        ];
    }
}
