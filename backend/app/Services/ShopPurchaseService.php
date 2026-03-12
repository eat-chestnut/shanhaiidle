<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ShopGood;
use App\Models\ShopPlayerProfile;
use App\Models\ShopPurchaseLog;
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

        if (! Item::query()->where('id', $goods->reward_item_id)->exists()) {
            return ['ok' => false, 'reason' => 'reward_item_missing'];
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

            $now = CarbonImmutable::now();
            $availability = $this->checkAvailability($lockedGoods, $profile, $now);
            if (! $availability['ok']) {
                return $availability;
            }

            $costCurrency = (string) $lockedGoods->cost_currency_type;
            $costAmount = max(0, (int) $lockedGoods->cost_amount);
            $availableCurrency = $this->currencyAmount($profile, $costCurrency);
            if ($availableCurrency < $costAmount) {
                return [
                    'ok' => false,
                    'reason' => 'insufficient_currency',
                    'currency_type' => $costCurrency,
                    'need' => $costAmount,
                    'owned' => $availableCurrency,
                ];
            }

            $this->spendCurrency($profile, $costCurrency, $costAmount);
            $this->grantItem($profile, (string) $lockedGoods->reward_item_id, max(1, (int) $lockedGoods->reward_count));
            $profile->save();

            $dateBucket = $now->toDateString();
            $weekBucket = sprintf('%s-W%02d', $now->isoWeekYear(), $now->isoWeek());

            ShopPurchaseLog::query()->create([
                'player_id' => $safePlayerId,
                'goods_id' => (string) $lockedGoods->goods_id,
                'shop_type' => (string) $lockedGoods->shop_type,
                'reward_item_id' => (string) $lockedGoods->reward_item_id,
                'reward_count' => max(1, (int) $lockedGoods->reward_count),
                'cost_currency_type' => $costCurrency,
                'cost_amount' => $costAmount,
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
                'shop_type' => (string) $lockedGoods->shop_type,
                'reward_item_id' => (string) $lockedGoods->reward_item_id,
                'reward_count' => max(1, (int) $lockedGoods->reward_count),
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

        if ($goods->starts_at !== null && $now->lt($goods->starts_at)) {
            return ['ok' => false, 'reason' => 'not_started', 'starts_at' => $goods->starts_at?->toDateTimeString()];
        }

        if ($goods->ends_at !== null && $now->gt($goods->ends_at)) {
            return ['ok' => false, 'reason' => 'ended', 'ends_at' => $goods->ends_at?->toDateTimeString()];
        }

        $limits = $this->remainingLimits((string) $profile->player_id, $goods, $now);

        if (($limits['daily_remaining'] ?? null) === 0) {
            return ['ok' => false, 'reason' => 'daily_limit_reached', 'remaining_limits' => $limits];
        }
        if (($limits['weekly_remaining'] ?? null) === 0) {
            return ['ok' => false, 'reason' => 'weekly_limit_reached', 'remaining_limits' => $limits];
        }
        if (($limits['lifetime_remaining'] ?? null) === 0) {
            return ['ok' => false, 'reason' => 'lifetime_limit_reached', 'remaining_limits' => $limits];
        }

        return ['ok' => true];
    }

    private function remainingLimits(string $playerId, ShopGood $goods, CarbonImmutable $now): array
    {
        $dateBucket = $now->toDateString();
        $weekBucket = sprintf('%s-W%02d', $now->isoWeekYear(), $now->isoWeek());

        $baseQuery = ShopPurchaseLog::query()
            ->where('player_id', $playerId)
            ->where('goods_id', (string) $goods->goods_id);

        $lifetimePurchased = (int) (clone $baseQuery)->sum('quantity');
        $dailyPurchased = (int) (clone $baseQuery)->whereDate('reset_bucket_date', $dateBucket)->sum('quantity');
        $weeklyPurchased = (int) (clone $baseQuery)->where('reset_bucket_week', $weekBucket)->sum('quantity');

        return [
            'daily_limit' => $goods->daily_limit !== null ? (int) $goods->daily_limit : null,
            'daily_remaining' => $goods->daily_limit !== null ? max(0, (int) $goods->daily_limit - $dailyPurchased) : null,
            'weekly_limit' => $goods->weekly_limit !== null ? (int) $goods->weekly_limit : null,
            'weekly_remaining' => $goods->weekly_limit !== null ? max(0, (int) $goods->weekly_limit - $weeklyPurchased) : null,
            'lifetime_limit' => $goods->lifetime_limit !== null ? (int) $goods->lifetime_limit : null,
            'lifetime_remaining' => $goods->lifetime_limit !== null ? max(0, (int) $goods->lifetime_limit - $lifetimePurchased) : null,
        ];
    }

    private function currencyAmount(ShopPlayerProfile $profile, string $currencyType): int
    {
        return match ($currencyType) {
            'gold' => max(0, (int) $profile->gold),
            'crystal' => max(0, (int) $profile->crystal),
            'contribution' => max(0, (int) $profile->contribution),
            default => 0,
        };
    }

    private function spendCurrency(ShopPlayerProfile $profile, string $currencyType, int $amount): void
    {
        $safeAmount = max(0, $amount);

        match ($currencyType) {
            'gold' => $profile->gold = max(0, (int) $profile->gold - $safeAmount),
            'crystal' => $profile->crystal = max(0, (int) $profile->crystal - $safeAmount),
            'contribution' => $profile->contribution = max(0, (int) $profile->contribution - $safeAmount),
            default => null,
        };
    }

    private function grantItem(ShopPlayerProfile $profile, string $itemId, int $count): void
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
