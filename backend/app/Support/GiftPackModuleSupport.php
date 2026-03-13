<?php

namespace App\Support;

use App\Models\GiftPack;
use App\Models\GiftPackItem;
use App\Models\Item;
use Illuminate\Validation\ValidationException;

class GiftPackModuleSupport
{
    public static function normalizePack(array $row): array
    {
        $pack = [
            'pack_id' => trim((string) ($row['pack_id'] ?? '')),
            'item_id' => trim((string) ($row['item_id'] ?? '')),
            'pack_name' => trim((string) ($row['pack_name'] ?? '')),
            'display_name' => trim((string) ($row['display_name'] ?? '')),
            'pack_type' => trim((string) ($row['pack_type'] ?? 'stage_reward_pack')),
            'pack_mode' => trim((string) ($row['pack_mode'] ?? 'fixed')),
            'open_mode' => trim((string) ($row['open_mode'] ?? 'manual')),
            'select_count_min' => filled($row['select_count_min'] ?? null) ? max(0, (int) $row['select_count_min']) : null,
            'select_count_max' => filled($row['select_count_max'] ?? null) ? max(0, (int) $row['select_count_max']) : null,
            'desc' => filled($row['desc'] ?? null) ? trim((string) $row['desc']) : null,
            'icon' => filled($row['icon'] ?? null) ? trim((string) $row['icon']) : null,
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
        ];

        return self::normalizeSelectRule($pack);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function normalizePackItems(mixed $raw, string $contentMode): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $rows[] = [
                'item_id' => $itemId,
                'content_mode' => $contentMode,
                'count_min' => max(1, (int) ($row['count_min'] ?? 1)),
                'count_max' => max(1, (int) ($row['count_max'] ?? 1)),
                'weight' => max(0, (int) ($row['weight'] ?? 0)),
                'recommended_sect' => $contentMode === 'selectable'
                    ? trim((string) ($row['recommended_sect'] ?? 'none'))
                    : 'none',
                'display_note' => $contentMode === 'selectable' && filled($row['display_note'] ?? null)
                    ? trim((string) $row['display_note'])
                    : null,
                'sort_order' => max(0, (int) ($row['sort_order'] ?? (($index + 1) * 10))),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        return array_values($rows);
    }

    public static function validatePackOrFail(array $pack, array $fixedItems, array $selectableItems): void
    {
        $errors = [];
        $itemLookup = self::itemLookup();

        if ($pack['pack_id'] === '') {
            $errors['pack_id'] = '请填写礼包 ID。';
        }

        if ($pack['pack_name'] === '') {
            $errors['pack_name'] = '请填写礼包内部名称。';
        }

        if ($pack['display_name'] === '') {
            $errors['display_name'] = '请填写礼包展示名称。';
        }

        if (! isset(GiftPack::PACK_TYPE_OPTIONS[$pack['pack_type']])) {
            $errors['pack_type'] = '礼包类型非法。';
        }

        if (! isset(GiftPack::PACK_MODE_OPTIONS[$pack['pack_mode']])) {
            $errors['pack_mode'] = '礼包形式非法。';
        }

        if (! isset(GiftPack::OPEN_MODE_OPTIONS[$pack['open_mode']])) {
            $errors['open_mode'] = '开启方式非法。';
        }

        $carrierItem = $itemLookup[$pack['item_id']] ?? null;
        if ($pack['item_id'] === '') {
            $errors['item_id'] = '请选择礼包物品。';
        } elseif ($carrierItem === null) {
            $errors['item_id'] = '礼包物品不存在。';
        } elseif ((string) ($carrierItem['main_type'] ?? '') !== 'gift_pack') {
            $errors['item_id'] = '礼包物品必须是 gift_pack 类型 item。';
        } elseif (! (bool) ($carrierItem['is_enabled'] ?? false)) {
            $errors['item_id'] = '礼包物品必须为启用状态。';
        }

        $enabledFixedItems = array_values(array_filter($fixedItems, fn (array $row): bool => (bool) ($row['is_enabled'] ?? true)));
        $enabledSelectableItems = array_values(array_filter($selectableItems, fn (array $row): bool => (bool) ($row['is_enabled'] ?? true)));

        foreach ($fixedItems as $index => $row) {
            self::validatePackItemRow($errors, "fixed_items.{$index}", $row, $itemLookup, false);
        }

        foreach ($selectableItems as $index => $row) {
            self::validatePackItemRow($errors, "selectable_items.{$index}", $row, $itemLookup, true);
        }

        if ($pack['pack_mode'] === 'fixed') {
            if ($enabledFixedItems === []) {
                $errors['fixed_items'] = '固定礼包至少需要 1 条启用的固定内容。';
            }

            if ($selectableItems !== []) {
                $errors['selectable_items'] = 'fixed 礼包不能配置自选内容。';
            }
        }

        if ($pack['pack_mode'] === 'select_one') {
            if (($pack['select_count_min'] ?? null) !== 1 || ($pack['select_count_max'] ?? null) !== 1) {
                $errors['select_count_min'] = 'select_one 必须固定为选 1 个。';
            }

            if ($enabledSelectableItems === []) {
                $errors['selectable_items'] = '自选礼包必须至少配置 1 条启用的自选内容。';
            }
        }

        if ($pack['pack_mode'] === 'select_multi') {
            if ($enabledSelectableItems === []) {
                $errors['selectable_items'] = '多选礼包必须至少配置 1 条启用的自选内容。';
            }

            $selectMin = (int) ($pack['select_count_min'] ?? 0);
            $selectMax = (int) ($pack['select_count_max'] ?? 0);
            if ($selectMin < 1) {
                $errors['select_count_min'] = 'select_multi 的最少选择数必须大于等于 1。';
            }
            if ($selectMax < $selectMin) {
                $errors['select_count_max'] = 'select_multi 的最大选择数不能小于最少选择数。';
            }
            if ($enabledSelectableItems !== [] && $selectMax > count($enabledSelectableItems)) {
                $errors['select_count_max'] = 'select_multi 的最大选择数不能超过启用的自选条目数。';
            }
        }

        if ($enabledFixedItems === [] && $enabledSelectableItems === []) {
            $errors['fixed_items'] = '礼包至少需要 1 条启用内容。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function validateModuleRowsOrFail(array $packs, array $packItems): void
    {
        $errors = [];
        $seenPackIds = [];
        $seenItemIds = [];
        $normalizedPacks = [];

        foreach ($packs as $index => $row) {
            if (! is_array($row)) {
                $errors["gift_packs.{$index}"] = '礼包主表条目格式错误。';
                continue;
            }

            $pack = self::normalizePack($row);
            $normalizedPacks[] = $pack;

            if ($pack['pack_id'] === '') {
                $errors["gift_packs.{$index}.pack_id"] = '请填写 pack_id。';
            } elseif (isset($seenPackIds[$pack['pack_id']])) {
                $errors["gift_packs.{$index}.pack_id"] = sprintf('pack_id 重复：%s。', $pack['pack_id']);
            } else {
                $seenPackIds[$pack['pack_id']] = true;
            }

            if ($pack['item_id'] === '') {
                $errors["gift_packs.{$index}.item_id"] = '请填写 item_id。';
            } elseif (isset($seenItemIds[$pack['item_id']])) {
                $errors["gift_packs.{$index}.item_id"] = sprintf('礼包物品重复：%s。', $pack['item_id']);
            } else {
                $seenItemIds[$pack['item_id']] = true;
            }
        }

        $groupedItems = [];
        foreach ($packItems as $index => $row) {
            if (! is_array($row)) {
                $errors["gift_pack_items.{$index}"] = '礼包内容条目格式错误。';
                continue;
            }

            $packId = trim((string) ($row['pack_id'] ?? ''));
            if ($packId === '') {
                $errors["gift_pack_items.{$index}.pack_id"] = '请填写 pack_id。';
                continue;
            }

            $contentMode = trim((string) ($row['content_mode'] ?? ''));
            if (! isset(GiftPackItem::CONTENT_MODE_OPTIONS[$contentMode])) {
                $errors["gift_pack_items.{$index}.content_mode"] = 'content_mode 非法。';
                continue;
            }

            $groupedItems[$packId][$contentMode][] = $row;
        }

        foreach ($normalizedPacks as $index => $pack) {
            $group = $groupedItems[$pack['pack_id']] ?? [];
            $fixedItems = self::normalizePackItems($group['fixed'] ?? [], 'fixed');
            $selectableItems = self::normalizePackItems($group['selectable'] ?? [], 'selectable');

            try {
                self::validatePackOrFail($pack, $fixedItems, $selectableItems);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $errors["gift_packs.{$index}.{$field}"] = $messages[0];
                }
            }
        }

        foreach ($groupedItems as $packId => $group) {
            if (! isset($seenPackIds[$packId])) {
                $errors["gift_pack_items.{$packId}.pack_id"] = sprintf('礼包内容引用了不存在的 pack_id：%s。', $packId);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function normalizeSelectRule(array $pack): array
    {
        return match ($pack['pack_mode']) {
            'fixed' => array_merge($pack, [
                'select_count_min' => null,
                'select_count_max' => null,
            ]),
            'select_one' => array_merge($pack, [
                'select_count_min' => 1,
                'select_count_max' => 1,
            ]),
            'select_multi' => array_merge($pack, [
                'select_count_min' => max(1, (int) ($pack['select_count_min'] ?? 1)),
                'select_count_max' => max(1, (int) ($pack['select_count_max'] ?? ($pack['select_count_min'] ?? 1))),
            ]),
            default => $pack,
        };
    }

    /**
     * @param  array<string, string>  $errors
     * @param  array<string, array{main_type:string,is_enabled:bool}>  $itemLookup
     */
    private static function validatePackItemRow(array &$errors, string $fieldPrefix, array $row, array $itemLookup, bool $isSelectable): void
    {
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $countMin = (int) ($row['count_min'] ?? 0);
        $countMax = (int) ($row['count_max'] ?? 0);
        $recommendedSect = trim((string) ($row['recommended_sect'] ?? 'none'));

        if ($itemId === '') {
            $errors["{$fieldPrefix}.item_id"] = '请选择物品。';
        } elseif (! isset($itemLookup[$itemId])) {
            $errors["{$fieldPrefix}.item_id"] = '物品不存在。';
        } elseif ((string) ($itemLookup[$itemId]['main_type'] ?? '') === 'gift_pack') {
            $errors["{$fieldPrefix}.item_id"] = 'V1 不允许礼包内容继续发礼包。';
        }

        if ($countMin < 1) {
            $errors["{$fieldPrefix}.count_min"] = '最小数量必须大于等于 1。';
        }

        if ($countMax < $countMin) {
            $errors["{$fieldPrefix}.count_max"] = '最大数量不能小于最小数量。';
        }

        if ($isSelectable && ! isset(GiftPackItem::RECOMMENDED_SECT_OPTIONS[$recommendedSect])) {
            $errors["{$fieldPrefix}.recommended_sect"] = '推荐宗门非法。';
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
