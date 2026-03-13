<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ShopGood;
use App\Models\ShopPlayerProfile;
use App\Models\ShopPurchaseLog;
use App\Support\ShopGoodsSupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ShopPurchaseService
{
    public function purchase(string $playerId, string $goodsId, int $quantity = 1, string $operatorType = 'player'): array
    {
        $safePlayerId = trim($playerId);
        $safeGoodsId = trim($goodsId);
        $safeOperatorType = trim($operatorType) !== '' ? trim($operatorType) : 'player';

        if ($safePlayerId === '' || $safeGoodsId === '') {
            return ['ok' => false, 'reason' => 'invalid_payload'];
        }

        if ($quantity !== 1) {
            return ['ok' => false, 'reason' => 'invalid_quantity'];
        }

        $goods = ShopGood::query()->where('goods_id', $safeGoodsId)->first();
        if (! $goods instanceof ShopGood) {
            return ['ok' => false, 'reason' => 'goods_not_found'];
        }

        ShopPlayerProfile::query()->firstOrCreate(
            ['player_id' => $safePlayerId],
            [
                'level' => 1,
                'exp' => 0,
                'gold' => 0,
                'crystal' => 0,
                'contribution' => 0,
                'free_attr_points' => 0,
                'skill_points' => 0,
                'inventory' => [],
                'equipment' => [],
                'claimed_milestones' => [],
            ],
        );

        return DB::transaction(function () use ($safePlayerId, $safeGoodsId, $safeOperatorType): array {
            /** @var ShopGood|null $lockedGoods */
            $lockedGoods = ShopGood::query()
                ->where('goods_id', $safeGoodsId)
                ->lockForUpdate()
                ->first();

            /** @var ShopPlayerProfile|null $profile */
            $profile = ShopPlayerProfile::query()
                ->where('player_id', $safePlayerId)
                ->lockForUpdate()
                ->first();

            if (! $lockedGoods instanceof ShopGood || ! $profile instanceof ShopPlayerProfile) {
                return ['ok' => false, 'reason' => 'purchase_context_missing'];
            }

            /** @var Item|null $rewardItem */
            $rewardItem = Item::query()->where('item_id', (string) $lockedGoods->reward_item_id)->first();
            /** @var Item|null $priceItem */
            $priceItem = Item::query()->where('item_id', (string) $lockedGoods->price_item_id)->first();

            $runtimeValidation = $this->validateGoodsRuntime($lockedGoods, $rewardItem, $priceItem);
            if (! $runtimeValidation['ok']) {
                return $runtimeValidation;
            }

            $now = CarbonImmutable::now();
            $availability = $this->checkAvailability($lockedGoods, $profile, $now);
            if (! $availability['ok']) {
                return $availability;
            }

            $priceItemId = (string) $lockedGoods->price_item_id;
            $priceAmount = max(0, (int) $lockedGoods->price_amount);
            $availableCurrency = $this->currencyAmount($profile, $priceItemId);
            if ($availableCurrency < $priceAmount) {
                return [
                    'ok' => false,
                    'reason' => 'insufficient_currency',
                    'price_item_id' => $priceItemId,
                    'need' => $priceAmount,
                    'owned' => $availableCurrency,
                ];
            }

            $this->spendCurrency($profile, $priceItemId, $priceAmount);
            $this->grantReward($profile, $rewardItem, max(1, (int) $lockedGoods->reward_count));
            $profile->save();

            $dateBucket = $now->toDateString();
            $weekBucket = sprintf('%s-W%02d', $now->isoWeekYear(), $now->isoWeek());

            ShopPurchaseLog::query()->create([
                'player_id' => $safePlayerId,
                'goods_id' => (string) $lockedGoods->goods_id,
                'shop_tab' => (string) $lockedGoods->shop_tab,
                'goods_type' => (string) $lockedGoods->goods_type,
                'reward_item_id' => (string) $lockedGoods->reward_item_id,
                'reward_count' => max(1, (int) $lockedGoods->reward_count),
                'price_item_id' => $priceItemId,
                'price_amount' => $priceAmount,
                'quantity' => 1,
                'purchased_at' => $now,
                'reset_bucket_date' => $dateBucket,
                'reset_bucket_week' => $weekBucket,
                'operator_type' => $safeOperatorType,
                'created_at' => $now,
            ]);

            return [
                'ok' => true,
                'goods_id' => (string) $lockedGoods->goods_id,
                'shop_tab' => (string) $lockedGoods->shop_tab,
                'goods_type' => (string) $lockedGoods->goods_type,
                'reward_item_id' => (string) $lockedGoods->reward_item_id,
                'reward_count' => max(1, (int) $lockedGoods->reward_count),
                'price_item_id' => $priceItemId,
                'price_amount' => $priceAmount,
                'granted_items' => [[
                    'item_id' => (string) $lockedGoods->reward_item_id,
                    'count' => max(1, (int) $lockedGoods->reward_count),
                ]],
                'updated_currencies' => $this->currencySnapshot($profile),
                'remaining_limits' => $this->remainingLimits($safePlayerId, $lockedGoods, $now),
                'profile' => [
                    'player_id' => (string) $profile->player_id,
                    'level' => (int) $profile->level,
                ],
            ];
        });
    }

    public function upsertPlayerProfile(string $playerId, array $payload): ShopPlayerProfile
    {
        return app(PlayerProfileSyncService::class)->upsertSnapshot($playerId, $payload);
    }

    private function validateGoodsRuntime(ShopGood $goods, ?Item $rewardItem, ?Item $priceItem): array
    {
        if (! $rewardItem instanceof Item || ! (bool) $rewardItem->is_enabled) {
            return ['ok' => false, 'reason' => 'reward_item_missing'];
        }

        if (! $priceItem instanceof Item || ! (bool) $priceItem->is_enabled) {
            return ['ok' => false, 'reason' => 'price_item_missing'];
        }

        if ((string) $goods->goods_type === 'gift_pack' && (string) $rewardItem->main_type !== 'gift_pack') {
            return ['ok' => false, 'reason' => 'invalid_goods_config'];
        }

        if ((string) $goods->goods_type === 'direct_item' && (string) $rewardItem->main_type === 'gift_pack') {
            return ['ok' => false, 'reason' => 'invalid_goods_config'];
        }

        if ((string) $priceItem->main_type !== 'currency') {
            return ['ok' => false, 'reason' => 'invalid_price_item'];
        }

        if (ShopGoodsSupport::profileCurrencyField((string) $goods->price_item_id) === null) {
            return ['ok' => false, 'reason' => 'unsupported_price_item'];
        }

        if (! isset(ShopGood::SHOP_TAB_OPTIONS[(string) $goods->shop_tab])) {
            return ['ok' => false, 'reason' => 'invalid_goods_config'];
        }

        if (! isset(ShopGood::GOODS_TYPE_OPTIONS[(string) $goods->goods_type])) {
            return ['ok' => false, 'reason' => 'invalid_goods_config'];
        }

        if (! isset(ShopGood::BUY_LIMIT_TYPE_OPTIONS[(string) $goods->buy_limit_type])) {
            return ['ok' => false, 'reason' => 'invalid_goods_config'];
        }

        return ['ok' => true];
    }

    private function checkAvailability(ShopGood $goods, ShopPlayerProfile $profile, CarbonImmutable $now): array
    {
        if (! (bool) $goods->is_enabled) {
            return ['ok' => false, 'reason' => 'disabled'];
        }

        if ((int) $profile->level < max(1, (int) $goods->unlock_level)) {
            return [
                'ok' => false,
                'reason' => 'level_locked',
                'need_level' => max(1, (int) $goods->unlock_level),
                'current_level' => (int) $profile->level,
            ];
        }

        $limits = $this->remainingLimits((string) $profile->player_id, $goods, $now);
        if (($limits['buy_limit_type'] ?? 'none') !== 'none' && ($limits['remaining'] ?? null) === 0) {
            return [
                'ok' => false,
                'reason' => sprintf('%s_limit_reached', (string) $limits['buy_limit_type']),
                'remaining_limits' => $limits,
            ];
        }

        return ['ok' => true];
    }

    private function remainingLimits(string $playerId, ShopGood $goods, CarbonImmutable $now): array
    {
        $buyLimitType = (string) $goods->buy_limit_type;
        $buyLimitValue = max(0, (int) $goods->buy_limit_value);

        if ($buyLimitType === 'none' || $buyLimitValue === 0) {
            return [
                'buy_limit_type' => 'none',
                'buy_limit_value' => 0,
                'purchased_count' => 0,
                'remaining' => null,
            ];
        }

        $dateBucket = $now->toDateString();
        $weekBucket = sprintf('%s-W%02d', $now->isoWeekYear(), $now->isoWeek());

        $baseQuery = ShopPurchaseLog::query()
            ->where('player_id', $playerId)
            ->where('goods_id', (string) $goods->goods_id);

        $purchasedCount = match ($buyLimitType) {
            'daily' => (int) (clone $baseQuery)->whereDate('reset_bucket_date', $dateBucket)->sum('quantity'),
            'weekly' => (int) (clone $baseQuery)->where('reset_bucket_week', $weekBucket)->sum('quantity'),
            'lifetime' => (int) (clone $baseQuery)->sum('quantity'),
            default => 0,
        };

        return [
            'buy_limit_type' => $buyLimitType,
            'buy_limit_value' => $buyLimitValue,
            'purchased_count' => $purchasedCount,
            'remaining' => max(0, $buyLimitValue - $purchasedCount),
        ];
    }

    private function currencyAmount(ShopPlayerProfile $profile, string $priceItemId): int
    {
        return match (ShopGoodsSupport::profileCurrencyField($priceItemId)) {
            'gold' => max(0, (int) $profile->gold),
            'crystal' => max(0, (int) $profile->crystal),
            'contribution' => max(0, (int) $profile->contribution),
            default => 0,
        };
    }

    private function spendCurrency(ShopPlayerProfile $profile, string $priceItemId, int $amount): void
    {
        $safeAmount = max(0, $amount);

        match (ShopGoodsSupport::profileCurrencyField($priceItemId)) {
            'gold' => $profile->gold = max(0, (int) $profile->gold - $safeAmount),
            'crystal' => $profile->crystal = max(0, (int) $profile->crystal - $safeAmount),
            'contribution' => $profile->contribution = max(0, (int) $profile->contribution - $safeAmount),
            default => null,
        };
    }

    private function grantReward(ShopPlayerProfile $profile, Item $rewardItem, int $count): void
    {
        $safeCount = max(0, $count);
        if ($safeCount <= 0) {
            return;
        }

        $currencyField = ShopGoodsSupport::profileCurrencyField((string) $rewardItem->item_id);
        if ((string) $rewardItem->main_type === 'currency' && $currencyField !== null) {
            $this->addCurrency($profile, $currencyField, $safeCount);

            return;
        }

        $this->grantInventoryItem($profile, (string) $rewardItem->item_id, $safeCount);
    }

    private function addCurrency(ShopPlayerProfile $profile, string $currencyField, int $amount): void
    {
        $safeAmount = max(0, $amount);

        match ($currencyField) {
            'gold' => $profile->gold = max(0, (int) $profile->gold + $safeAmount),
            'crystal' => $profile->crystal = max(0, (int) $profile->crystal + $safeAmount),
            'contribution' => $profile->contribution = max(0, (int) $profile->contribution + $safeAmount),
            default => null,
        };
    }

    private function grantInventoryItem(ShopPlayerProfile $profile, string $itemId, int $count): void
    {
        $inventory = is_array($profile->inventory) ? $profile->inventory : [];
        $safeItemId = trim($itemId);
        $safeCount = max(0, $count);

        if ($safeItemId === '' || $safeCount <= 0) {
            return;
        }

        $inventory[$safeItemId] = max(0, (int) ($inventory[$safeItemId] ?? 0)) + $safeCount;
        $profile->inventory = $this->normalizeInventory($inventory);
    }

    private function normalizeInventory(array $inventory): array
    {
        $normalized = [];
        foreach ($inventory as $itemId => $count) {
            $safeItemId = trim((string) $itemId);
            $safeCount = max(0, (int) $count);
            if ($safeItemId === '' || $safeCount <= 0) {
                continue;
            }
            $normalized[$safeItemId] = $safeCount;
        }

        ksort($normalized);

        return $normalized;
    }

    private function currencySnapshot(ShopPlayerProfile $profile): array
    {
        return [
            'gold' => max(0, (int) $profile->gold),
            'crystal' => max(0, (int) $profile->crystal),
            'contribution' => max(0, (int) $profile->contribution),
        ];
    }
}
