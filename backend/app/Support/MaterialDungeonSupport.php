<?php

namespace App\Support;

use App\Models\Item;
use App\Models\MaterialDungeonDropGroup;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaterialDungeonSupport
{
    /**
     * @var array<string, array{id:string,name:string,icon:string,rarity:string,rarity_name:string}>
     */
    private static array $itemMetaCache = [];

    public static function displayRewardsForForm(mixed $stored): array
    {
        $rows = [];

        foreach (is_array($stored) ? $stored : [] as $itemId) {
            $itemId = trim((string) $itemId);
            if ($itemId === '') {
                continue;
            }

            $rows[] = ['item_id' => $itemId];
        }

        return $rows;
    }

    public static function normalizeDisplayRewardsOrFail(mixed $rows): array
    {
        $errors = [];
        $normalized = [];
        $seen = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            $field = "display_rewards.{$index}.item_id";

            if ($itemId === '') {
                $errors[$field] = '请选择展示掉落物品。';
                continue;
            }

            if (! static::itemExists($itemId)) {
                $errors[$field] = '展示掉落物品无效。';
                continue;
            }

            if (isset($seen[$itemId])) {
                $errors[$field] = sprintf('展示掉落中存在重复物品：%s。', static::itemName($itemId));
                continue;
            }

            $seen[$itemId] = true;
            $normalized[] = $itemId;
        }

        if ($normalized === []) {
            $errors['display_rewards'] = '请至少配置一个展示掉落物品。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public static function layerRulesForForm(mixed $stored): array
    {
        $rows = [];

        foreach (is_array($stored) ? $stored : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'layer' => (int) ($row['layer'] ?? ($index + 1)),
                'drop_group_id' => filled($row['drop_group_id'] ?? null) ? (string) $row['drop_group_id'] : null,
                'first_clear_reward_group_id' => filled($row['first_clear_reward_group_id'] ?? null) ? (string) $row['first_clear_reward_group_id'] : null,
                'recommended_power' => filled($row['recommended_power'] ?? null) ? (int) $row['recommended_power'] : null,
                'sort' => (int) ($row['sort'] ?? ($index + 1)),
            ];
        }

        return $rows;
    }

    public static function levelConfigsForForm(mixed $stored): array
    {
        $rows = [];

        foreach (is_array($stored) ? $stored : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $costs = [];
            foreach (is_array($row['upgrade_costs'] ?? null) ? $row['upgrade_costs'] : [] as $costIndex => $costRow) {
                if (! is_array($costRow)) {
                    continue;
                }

                $costs[] = [
                    'item_id' => filled($costRow['item_id'] ?? null) ? (string) $costRow['item_id'] : null,
                    'count' => (int) ($costRow['count'] ?? 1),
                    'sort' => (int) ($costRow['sort'] ?? ($costIndex + 1)),
                ];
            }

            $rows[] = [
                'level' => (int) ($row['level'] ?? ($index + 1)),
                'reward_multiplier' => static::normalizeNumber($row['reward_multiplier'] ?? 1),
                'upgrade_costs' => $costs,
                'sort' => (int) ($row['sort'] ?? ($index + 1)),
            ];
        }

        return $rows;
    }

    public static function normalizeLevelConfigsOrFail(mixed $rows): array
    {
        $errors = [];
        $normalized = [];
        $expectedLevel = 1;

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $level = $row['level'] ?? null;
            $rewardMultiplier = $row['reward_multiplier'] ?? null;
            $costRows = is_array($row['upgrade_costs'] ?? null) ? $row['upgrade_costs'] : [];

            if (! is_numeric($level) || (int) $level !== $expectedLevel) {
                $errors["level_configs.{$index}.level"] = sprintf('副本等级必须从 1 开始连续配置，当前期望等级为 %d。', $expectedLevel);
            } else {
                $level = (int) $level;
                $expectedLevel++;
            }

            if (! is_numeric($rewardMultiplier) || (float) $rewardMultiplier < 1) {
                $errors["level_configs.{$index}.reward_multiplier"] = '奖励倍率必须大于等于 1。';
            }

            $normalizedCosts = [];
            $seenItems = [];

            foreach ($costRows as $costIndex => $costRow) {
                if (! is_array($costRow)) {
                    continue;
                }

                $itemId = trim((string) ($costRow['item_id'] ?? ''));
                $count = $costRow['count'] ?? null;

                if ($itemId === '') {
                    $errors["level_configs.{$index}.upgrade_costs.{$costIndex}.item_id"] = '请选择升级材料。';
                    continue;
                }

                if (! static::itemExists($itemId)) {
                    $errors["level_configs.{$index}.upgrade_costs.{$costIndex}.item_id"] = '升级材料无效。';
                    continue;
                }

                if (isset($seenItems[$itemId])) {
                    $errors["level_configs.{$index}.upgrade_costs.{$costIndex}.item_id"] = sprintf('副本等级 %d 的升级消耗存在重复材料：%s。', (int) ($level ?? 0), static::itemName($itemId));
                    continue;
                }

                if (! is_numeric($count) || (int) $count < 1) {
                    $errors["level_configs.{$index}.upgrade_costs.{$costIndex}.count"] = '升级材料数量必须大于等于 1。';
                    continue;
                }

                $seenItems[$itemId] = true;
                $normalizedCosts[] = [
                    'item_id' => $itemId,
                    'count' => (int) $count,
                    'sort' => count($normalizedCosts) + 1,
                ];
            }

            if (($level ?? 1) > 1 && $normalizedCosts === []) {
                $errors["level_configs.{$index}.upgrade_costs"] = sprintf('副本等级 %d 需要至少配置一种升级材料。', (int) ($level ?? 0));
            }

            $normalized[] = [
                'level' => (int) ($level ?? 0),
                'reward_multiplier' => static::normalizeNumber($rewardMultiplier ?? 1),
                'upgrade_costs' => $normalizedCosts,
                'sort' => $index + 1,
            ];
        }

        if ($normalized === []) {
            $errors['level_configs'] = '请至少配置一个副本等级。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public static function normalizeLayerRulesOrFail(mixed $rows): array
    {
        $errors = [];
        $normalized = [];
        $seenLayers = [];
        $groupIds = static::enabledDropGroupIds();

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $layer = $row['layer'] ?? null;
            $dropGroupId = trim((string) ($row['drop_group_id'] ?? ''));
            $firstClearGroupId = trim((string) ($row['first_clear_reward_group_id'] ?? ''));
            $recommendedPower = $row['recommended_power'] ?? null;

            if (! is_numeric($layer) || (int) $layer < 1) {
                $errors["layer_rules.{$index}.layer"] = '层级必须是大于 0 的整数。';
            } else {
                $layer = (int) $layer;
                if (isset($seenLayers[$layer])) {
                    $errors["layer_rules.{$index}.layer"] = sprintf('层级 %d 重复，请检查层级掉落规则。', $layer);
                }
                $seenLayers[$layer] = true;
            }

            if ($dropGroupId === '') {
                $errors["layer_rules.{$index}.drop_group_id"] = '请选择掉落组。';
            } elseif (! in_array($dropGroupId, $groupIds, true)) {
                $errors["layer_rules.{$index}.drop_group_id"] = '掉落组无效或未启用。';
            }

            if ($firstClearGroupId !== '' && ! in_array($firstClearGroupId, $groupIds, true)) {
                $errors["layer_rules.{$index}.first_clear_reward_group_id"] = '首通奖励组无效或未启用。';
            }

            if ($recommendedPower !== null && $recommendedPower !== '') {
                if (! is_numeric($recommendedPower) || (int) $recommendedPower < 0) {
                    $errors["layer_rules.{$index}.recommended_power"] = '推荐战力必须是不小于 0 的整数。';
                } else {
                    $recommendedPower = (int) $recommendedPower;
                }
            } else {
                $recommendedPower = null;
            }

            $normalized[] = [
                'layer' => (int) ($layer ?? 0),
                'drop_group_id' => $dropGroupId,
                'first_clear_reward_group_id' => $firstClearGroupId !== '' ? $firstClearGroupId : null,
                'recommended_power' => $recommendedPower,
                'sort' => $index + 1,
            ];
        }

        if ($normalized === []) {
            $errors['layer_rules'] = '请至少配置一条层级掉落规则。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public static function dropGroupRewardsForForm(mixed $stored): array
    {
        $rows = [];

        foreach (is_array($stored) ? $stored : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'item_id' => filled($row['item_id'] ?? null) ? (string) $row['item_id'] : null,
                'count_min' => (int) ($row['count_min'] ?? 1),
                'count_max' => (int) ($row['count_max'] ?? 1),
                'probability' => $row['probability'] ?? 1,
                'sort' => (int) ($row['sort'] ?? ($index + 1)),
            ];
        }

        return $rows;
    }

    public static function normalizeDropGroupRewardsOrFail(mixed $rows): array
    {
        $errors = [];
        $normalized = [];
        $seen = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            $countMin = $row['count_min'] ?? null;
            $countMax = $row['count_max'] ?? null;
            $probability = $row['probability'] ?? null;

            if ($itemId === '') {
                $errors["rewards.{$index}.item_id"] = '请选择掉落物品。';
            } elseif (! static::itemExists($itemId)) {
                $errors["rewards.{$index}.item_id"] = '掉落物品无效。';
            } elseif (isset($seen[$itemId])) {
                $errors["rewards.{$index}.item_id"] = sprintf('掉落组中存在重复物品：%s。', static::itemName($itemId));
            } else {
                $seen[$itemId] = true;
            }

            if (! is_numeric($countMin) || (int) $countMin < 1) {
                $errors["rewards.{$index}.count_min"] = '最少数量必须大于等于 1。';
            }

            if (! is_numeric($countMax) || (int) $countMax < 1) {
                $errors["rewards.{$index}.count_max"] = '最多数量必须大于等于 1。';
            }

            if (is_numeric($countMin) && is_numeric($countMax) && (int) $countMax < (int) $countMin) {
                $errors["rewards.{$index}.count_max"] = '最多数量不能小于最少数量。';
            }

            if (! is_numeric($probability) || (float) $probability <= 0 || (float) $probability > 1) {
                $errors["rewards.{$index}.probability"] = '概率必须大于 0 且不超过 1。';
            }

            $normalized[] = [
                'item_id' => $itemId,
                'count_min' => (int) $countMin,
                'count_max' => (int) $countMax,
                'probability' => static::normalizeNumber($probability),
                'sort' => $index + 1,
            ];
        }

        if ($normalized === []) {
            $errors['rewards'] = '请至少配置一条掉落条目。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public static function displayRewardDetails(array $itemIds): array
    {
        return array_values(array_filter(array_map(
            fn (string $itemId): ?array => static::itemExists($itemId) ? static::itemMeta($itemId) : null,
            array_values(array_unique(array_filter(array_map(fn ($itemId): string => trim((string) $itemId), $itemIds)))),
        )));
    }

    public static function exportDropGroupRewards(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            $itemId = (string) ($row['item_id'] ?? '');
            $meta = static::itemMeta($itemId);

            return [
                'item_id' => $itemId,
                'item_name' => $meta['name'],
                'icon' => $meta['icon'],
                'rarity' => $meta['rarity'],
                'rarity_name' => $meta['rarity_name'],
                'count_min' => (int) ($row['count_min'] ?? 1),
                'count_max' => (int) ($row['count_max'] ?? 1),
                'probability' => static::normalizeNumber($row['probability'] ?? 1),
                'sort' => (int) ($row['sort'] ?? 0),
            ];
        }, $rows));
    }

    public static function resolveLegacyItemId(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $aliases = [
            '箕尾镇脉石碎片' => '箕尾镇脉石',
        ];

        $candidate = $aliases[$raw] ?? $raw;
        if (static::itemExists($candidate)) {
            return $candidate;
        }

        $item = Item::query()
            ->where('display_name', $candidate)
            ->where('is_enabled', true)
            ->value('item_id');

        return filled($item) ? (string) $item : null;
    }

    public static function itemMeta(?string $itemId): array
    {
        $itemId = trim((string) $itemId);
        if ($itemId === '') {
            return [
                'item_id' => '',
                'name' => '',
                'icon' => '',
                'rarity' => '',
                'rarity_name' => '—',
            ];
        }

        if (! isset(static::$itemMetaCache[$itemId])) {
            $row = Item::query()
                ->where('item_id', $itemId)
                ->where('is_enabled', true)
                ->first(['item_id', 'display_name', 'icon', 'rarity']);

            static::$itemMetaCache[$itemId] = [
                'item_id' => $itemId,
                'name' => (string) ($row?->display_name ?? $itemId),
                'icon' => (string) ($row?->icon ?? ''),
                'rarity' => (string) ($row?->rarity ?? ''),
                'rarity_name' => AdminOptions::optionLabel(AdminOptions::rarityOptions(), $row?->rarity),
            ];
        }

        return static::$itemMetaCache[$itemId];
    }

    public static function itemName(?string $itemId): string
    {
        return static::itemMeta($itemId)['name'];
    }

    public static function itemRarityLabel(?string $itemId): string
    {
        return static::itemMeta($itemId)['rarity_name'];
    }

    public static function itemIcon(?string $itemId): string
    {
        return static::itemMeta($itemId)['icon'];
    }

    private static function itemExists(string $itemId): bool
    {
        return static::itemMeta($itemId)['name'] !== $itemId || Item::query()->where('item_id', $itemId)->where('is_enabled', true)->exists();
    }

    /**
     * @return array<int, string>
     */
    private static function enabledDropGroupIds(): array
    {
        return MaterialDungeonDropGroup::query()
            ->where('is_enabled', true)
            ->pluck('group_id')
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();
    }

    private static function normalizeNumber(mixed $value): int|float
    {
        $number = (float) $value;

        return abs($number - floor($number)) < 0.000001 ? (int) round($number) : round($number, 4);
    }
}
