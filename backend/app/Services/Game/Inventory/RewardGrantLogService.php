<?php

namespace App\Services\Game\Inventory;

use App\Models\RewardGrantLog;

class RewardGrantLogService
{
    public const SUPPORTED_REWARD_SOURCE_TYPES = [
        'main_stage_first_clear',
        'monster_drop_config',
        'battle_settlement',
    ];

    /**
     * @param array<int, array<string, mixed>> $rewardItems
     */
    public function record(
        string|int $playerId,
        string $battleId,
        array $rewardItems,
        string $rewardSourceType,
        string $rewardSourceId,
        string $grantBatchId,
    ): array {
        $safePlayerId = trim((string) $playerId);
        $safeBattleId = trim($battleId);
        $safeRewardSourceType = trim($rewardSourceType);
        $safeRewardSourceId = trim($rewardSourceId);
        $safeGrantBatchId = trim($grantBatchId);

        if ($safePlayerId === '' || $safeBattleId === '' || $safeGrantBatchId === '') {
            return $this->failure('invalid_reward_log_context');
        }

        $normalizedItemsResult = $this->normalizeRewardItems($rewardItems);
        if (! $normalizedItemsResult['ok']) {
            return $this->failure((string) $normalizedItemsResult['reason']);
        }

        $normalizedItems = $normalizedItemsResult['data']['reward_items'];

        foreach ($normalizedItems as $rewardItem) {
            $itemSourceType = trim((string) ($rewardItem['reward_source_type'] ?? $safeRewardSourceType));
            $itemSourceId = trim((string) ($rewardItem['reward_source_id'] ?? $safeRewardSourceId));

            if ($itemSourceType === '') {
                return $this->failure('reward_source_type is required');
            }

            if ($itemSourceId === '') {
                return $this->failure('reward_source_id is required');
            }

            if (! in_array($itemSourceType, self::SUPPORTED_REWARD_SOURCE_TYPES, true)) {
                return $this->failure(sprintf('unsupported reward_source_type %s', $itemSourceType));
            }

            RewardGrantLog::query()->create([
                'player_id' => $safePlayerId,
                'battle_id' => $safeBattleId,
                'item_id' => (string) $rewardItem['item_id'],
                'count' => (int) $rewardItem['count'],
                'reward_source_type' => $itemSourceType,
                'reward_source_id' => $itemSourceId,
                'grant_batch_id' => $safeGrantBatchId,
            ]);
        }

        return $this->success([
            'logged_count' => count($normalizedItems),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rewardItems
     */
    private function normalizeRewardItems(array $rewardItems): array
    {
        if ($rewardItems === []) {
            return $this->failure('reward_items is required');
        }

        $normalizedItems = [];

        foreach ($rewardItems as $index => $rewardItem) {
            if (! is_array($rewardItem)) {
                return $this->failure(sprintf('invalid reward item at index %d', $index));
            }

            $itemId = trim((string) ($rewardItem['item_id'] ?? ''));
            $count = (int) ($rewardItem['count'] ?? 0);

            if ($itemId === '') {
                return $this->failure(sprintf('invalid reward item at index %d: item_id is required', $index));
            }

            if ($count <= 0) {
                return $this->failure(sprintf('invalid reward item at index %d: count must be greater than 0', $index));
            }

            $row = [
                'item_id' => $itemId,
                'count' => $count,
            ];

            if (array_key_exists('reward_source_type', $rewardItem)) {
                $row['reward_source_type'] = trim((string) $rewardItem['reward_source_type']);
            }

            if (array_key_exists('reward_source_id', $rewardItem)) {
                $row['reward_source_id'] = trim((string) $rewardItem['reward_source_id']);
            }

            $normalizedItems[] = $row;
        }

        return $this->success([
            'reward_items' => $normalizedItems,
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

    private function failure(string $reason): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => null,
        ];
    }
}
