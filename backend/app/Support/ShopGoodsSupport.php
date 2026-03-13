<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ShopGood;
use Illuminate\Validation\ValidationException;

class ShopGoodsSupport
{
    /**
     * @return array<string, string>
     */
    public static function supportedPriceItemMap(): array
    {
        return [
            'cur_gold' => 'gold',
            'cur_premium_jade' => 'crystal',
            'cur_contribution' => 'contribution',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function supportedPriceItemIds(): array
    {
        return array_keys(self::supportedPriceItemMap());
    }

    public static function profileCurrencyField(?string $itemId): ?string
    {
        $safeItemId = trim((string) $itemId);

        return self::supportedPriceItemMap()[$safeItemId] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $normalized = [
            'goods_id' => trim((string) ($row['goods_id'] ?? '')),
            'title' => trim((string) ($row['title'] ?? '')),
            'display_name' => trim((string) ($row['display_name'] ?? '')),
            'shop_tab' => trim((string) ($row['shop_tab'] ?? 'daily')),
            'goods_type' => trim((string) ($row['goods_type'] ?? 'direct_item')),
            'reward_item_id' => trim((string) ($row['reward_item_id'] ?? '')),
            'reward_count' => max(1, (int) ($row['reward_count'] ?? 1)),
            'price_item_id' => trim((string) ($row['price_item_id'] ?? '')),
            'price_amount' => max(0, (int) ($row['price_amount'] ?? 0)),
            'original_price_amount' => filled($row['original_price_amount'] ?? null)
                ? max(0, (int) $row['original_price_amount'])
                : null,
            'unlock_level' => max(1, (int) ($row['unlock_level'] ?? 1)),
            'buy_limit_type' => trim((string) ($row['buy_limit_type'] ?? 'none')),
            'buy_limit_value' => filled($row['buy_limit_value'] ?? null)
                ? max(0, (int) $row['buy_limit_value'])
                : null,
            'is_recommended' => (bool) ($row['is_recommended'] ?? false),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'desc' => filled($row['desc'] ?? null) ? trim((string) $row['desc']) : null,
            'icon' => filled($row['icon'] ?? null) ? trim((string) $row['icon']) : null,
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
        ];

        if ($normalized['title'] === '' && $normalized['display_name'] !== '') {
            $normalized['title'] = $normalized['display_name'];
        }

        if ($normalized['display_name'] === '' && $normalized['title'] !== '') {
            $normalized['display_name'] = $normalized['title'];
        }

        if ($normalized['buy_limit_type'] === 'none') {
            $normalized['buy_limit_value'] = 0;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function validateRowOrFail(array $row): void
    {
        $errors = [];
        $itemLookup = self::itemLookup();
        $goods = self::normalizeRow($row);

        if ($goods['goods_id'] === '') {
            $errors['goods_id'] = '请填写商品 ID。';
        }

        if ($goods['title'] === '') {
            $errors['title'] = '请填写内部名称。';
        }

        if ($goods['display_name'] === '') {
            $errors['display_name'] = '请填写展示名称。';
        }

        if (! isset(ShopGood::SHOP_TAB_OPTIONS[$goods['shop_tab']])) {
            $errors['shop_tab'] = '商城页签非法。';
        }

        if (! isset(ShopGood::GOODS_TYPE_OPTIONS[$goods['goods_type']])) {
            $errors['goods_type'] = '商品类型非法。';
        }

        $rewardItem = $itemLookup[$goods['reward_item_id']] ?? null;
        if ($goods['reward_item_id'] === '') {
            $errors['reward_item_id'] = '请选择发放物品。';
        } elseif ($rewardItem === null) {
            $errors['reward_item_id'] = '发放物品不存在。';
        } elseif (! (bool) ($rewardItem['is_enabled'] ?? false)) {
            $errors['reward_item_id'] = '发放物品必须为启用状态。';
        } elseif ($goods['goods_type'] === 'gift_pack' && (string) ($rewardItem['main_type'] ?? '') !== 'gift_pack') {
            $errors['reward_item_id'] = 'gift_pack 商品必须发放 gift_pack 类型 item。';
        } elseif ($goods['goods_type'] === 'direct_item' && (string) ($rewardItem['main_type'] ?? '') === 'gift_pack') {
            $errors['reward_item_id'] = '多奖励商品必须通过礼包 item 承载，direct_item 不能直接卖礼包。';
        }

        $priceItem = $itemLookup[$goods['price_item_id']] ?? null;
        if ($goods['price_item_id'] === '') {
            $errors['price_item_id'] = '请选择价格货币。';
        } elseif ($priceItem === null) {
            $errors['price_item_id'] = '价格货币不存在。';
        } elseif (! (bool) ($priceItem['is_enabled'] ?? false)) {
            $errors['price_item_id'] = '价格货币必须为启用状态。';
        } elseif ((string) ($priceItem['main_type'] ?? '') !== 'currency') {
            $errors['price_item_id'] = '价格货币必须引用 currency 类型 item。';
        } elseif (self::profileCurrencyField($goods['price_item_id']) === null) {
            $errors['price_item_id'] = '当前购买链路仅支持 cur_gold / cur_premium_jade / cur_contribution 作为价格货币。';
        }

        if ($goods['price_amount'] < 0) {
            $errors['price_amount'] = '现价不能小于 0。';
        }

        if ($goods['original_price_amount'] !== null && $goods['original_price_amount'] < $goods['price_amount']) {
            $errors['original_price_amount'] = '原价不能小于现价。';
        }

        if ($goods['unlock_level'] < 1) {
            $errors['unlock_level'] = '解锁等级必须大于等于 1。';
        }

        if (! isset(ShopGood::BUY_LIMIT_TYPE_OPTIONS[$goods['buy_limit_type']])) {
            $errors['buy_limit_type'] = '限购类型非法。';
        }

        if ($goods['buy_limit_type'] === 'none') {
            if ((int) ($goods['buy_limit_value'] ?? 0) !== 0) {
                $errors['buy_limit_value'] = '不限购商品的限购次数应为 0。';
            }
        } elseif ((int) ($goods['buy_limit_value'] ?? 0) < 1) {
            $errors['buy_limit_value'] = '限购商品必须填写大于等于 1 的限购次数。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function validateRowsOrFail(array $rows): void
    {
        $errors = [];
        $seenGoodsIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["shop_goods.{$index}"] = '商品条目格式错误。';

                continue;
            }

            $goods = self::normalizeRow($row);
            if ($goods['goods_id'] === '') {
                $errors["shop_goods.{$index}.goods_id"] = '请填写 goods_id。';
            } elseif (isset($seenGoodsIds[$goods['goods_id']])) {
                $errors["shop_goods.{$index}.goods_id"] = sprintf('goods_id 重复：%s。', $goods['goods_id']);
            } else {
                $seenGoodsIds[$goods['goods_id']] = true;
            }

            try {
                self::validateRowOrFail($goods);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $errors["shop_goods.{$index}.{$field}"] = $messages[0];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, array{main_type:string,is_enabled:bool}>
     */
    private static function itemLookup(): array
    {
        return Item::query()
            ->get(['item_id', 'main_type', 'is_enabled'])
            ->mapWithKeys(fn (Item $item): array => [
                (string) $item->item_id => [
                    'main_type' => (string) $item->main_type,
                    'is_enabled' => (bool) $item->is_enabled,
                ],
            ])
            ->all();
    }
}
