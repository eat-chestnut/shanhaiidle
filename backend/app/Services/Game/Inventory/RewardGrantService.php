<?php

namespace App\Services\Game\Inventory;

use App\Models\Item;
use App\Models\PlayerCurrency;
use App\Models\PlayerItem;

class RewardGrantService
{
    /**
     * @param array<int, array<string, mixed>> $rewardItems
     */
    public function execute(string|int $playerId, array $rewardItems): array
    {
        $safePlayerId = trim((string) $playerId);
        if ($safePlayerId === '') {
            return $this->failure('invalid_player_id');
        }

        $normalizedItemsResult = $this->normalizeRewardItems($rewardItems);
        if (! $normalizedItemsResult['ok']) {
            return $this->failure((string) $normalizedItemsResult['reason']);
        }

        $normalizedItems = $normalizedItemsResult['data']['reward_items'];

        foreach ($normalizedItems as $rewardItem) {
            $itemId = (string) $rewardItem['item_id'];
            $count = (int) $rewardItem['count'];

            $item = Item::query()
                ->where('item_id', $itemId)
                ->where('is_enabled', true)
                ->first();

            if (! $item instanceof Item) {
                return $this->failure(sprintf('reward item not found: %s', $itemId));
            }

            $mainType = trim((string) $item->main_type);

            if ($mainType === 'currency') {
                $this->grantCurrency($safePlayerId, $itemId, $count);

                continue;
            }

            if ($this->isInventoryItemTypeSupported($mainType)) {
                $this->grantItem($safePlayerId, $itemId, $count);

                continue;
            }

            return $this->failure(sprintf(
                'unsupported reward item type %s for item_id %s',
                $mainType !== '' ? $mainType : 'unknown',
                $itemId,
            ));
        }

        return $this->success([
            'granted_items' => $normalizedItems,
        ]);
    }

    private function grantCurrency(string $playerId, string $currencyId, int $amount): void
    {
        $record = PlayerCurrency::query()->lockForUpdate()->firstOrCreate(
            [
                'player_id' => $playerId,
                'currency_id' => $currencyId,
            ],
            [
                'amount' => 0,
            ],
        );

        $record->amount = max(0, (int) $record->amount + $amount);
        $record->save();
    }

    private function grantItem(string $playerId, string $itemId, int $count): void
    {
        $record = PlayerItem::query()->lockForUpdate()->firstOrCreate(
            [
                'player_id' => $playerId,
                'item_id' => $itemId,
            ],
            [
                'count' => 0,
            ],
        );

        $record->count = max(0, (int) $record->count + $count);
        $record->save();
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

            $normalizedItems[] = [
                'item_id' => $itemId,
                'count' => $count,
            ];
        }

        return $this->success([
            'reward_items' => $normalizedItems,
        ]);
    }

    private function isInventoryItemTypeSupported(string $mainType): bool
    {
        return in_array($mainType, ['material', 'boss_core', 'consumable', 'item'], true);
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
