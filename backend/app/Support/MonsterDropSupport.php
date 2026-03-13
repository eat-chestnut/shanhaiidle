<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Validation\ValidationException;

class MonsterDropSupport
{
    /**
     * @var array<string, array{item_id:string,name:string}>
     */
    private static array $itemMetaCache = [];

    public static function dropsForForm(mixed $stored): array
    {
        return static::normalizeDrops($stored);
    }

    public static function normalizeDrops(mixed $raw): array
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

            $dropRate = $row['drop_rate'] ?? null;
            $rows[] = [
                'item_id' => $itemId,
                'count_min' => max(1, (int) ($row['count_min'] ?? 1)),
                'count_max' => max(1, (int) ($row['count_max'] ?? 1)),
                'drop_rate' => is_numeric($dropRate) ? static::normalizeNumber((float) $dropRate) : null,
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'sort' => $index + 1,
            ];
        }

        return array_values($rows);
    }

    public static function normalizeDropsOrFail(mixed $raw): array
    {
        $rows = static::normalizeDrops($raw);
        static::validateDropsOrFail($rows);

        return $rows;
    }

    public static function validateDropsOrFail(array $rows): void
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["drops.{$index}"] = '掉落配置格式错误。';
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            $countMin = $row['count_min'] ?? null;
            $countMax = $row['count_max'] ?? null;
            $dropRate = $row['drop_rate'] ?? null;

            if ($itemId === '') {
                $errors["drops.{$index}.item_id"] = '请选择掉落物品。';
            } elseif (! static::itemExists($itemId)) {
                $errors["drops.{$index}.item_id"] = '掉落物品无效。';
            }

            if (! is_numeric($countMin) || (int) $countMin < 1) {
                $errors["drops.{$index}.count_min"] = '最小数量必须大于等于 1。';
            }

            if (! is_numeric($countMax) || (int) $countMax < 1) {
                $errors["drops.{$index}.count_max"] = '最大数量必须大于等于 1。';
            }

            if (is_numeric($countMin) && is_numeric($countMax) && (int) $countMax < (int) $countMin) {
                $errors["drops.{$index}.count_max"] = '最大数量不能小于最小数量。';
            }

            if ($dropRate !== null && $dropRate !== '' && (! is_numeric($dropRate) || (float) $dropRate < 0 || (float) $dropRate > 1)) {
                $errors["drops.{$index}.drop_rate"] = '掉落概率必须在 0 到 1 之间。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function itemName(?string $itemId): string
    {
        $itemId = trim((string) $itemId);

        if ($itemId === '') {
            return '';
        }

        return static::itemMeta($itemId)['name'] ?? '';
    }

    public static function summary(mixed $raw): string
    {
        $rows = static::normalizeDrops($raw);
        $parts = [];

        foreach ($rows as $row) {
            $name = static::itemName((string) ($row['item_id'] ?? ''));
            if ($name === '') {
                continue;
            }

            $countMin = (int) ($row['count_min'] ?? 1);
            $countMax = (int) ($row['count_max'] ?? 1);
            $parts[] = $countMin === $countMax
                ? sprintf('%s x%d', $name, $countMin)
                : sprintf('%s x%d-%d', $name, $countMin, $countMax);
        }

        return implode('、', array_slice($parts, 0, 3));
    }

    /**
     * @return array{item_id:string,name:string}|array{}
     */
    private static function itemMeta(string $itemId): array
    {
        if ($itemId === '') {
            return [];
        }

        if (isset(static::$itemMetaCache[$itemId])) {
            return static::$itemMetaCache[$itemId];
        }

        $item = Item::query()
            ->where('item_id', $itemId)
            ->first(['item_id', 'display_name']);

        if (! $item) {
            return static::$itemMetaCache[$itemId] = [];
        }

        return static::$itemMetaCache[$itemId] = [
            'item_id' => (string) $item->item_id,
            'name' => (string) $item->display_name,
        ];
    }

    private static function itemExists(string $itemId): bool
    {
        return static::itemMeta($itemId) !== [];
    }

    private static function normalizeNumber(float $value): float|int
    {
        return floor($value) == $value ? (int) $value : round($value, 6);
    }
}
