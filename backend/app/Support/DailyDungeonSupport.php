<?php

namespace App\Support;

use App\Models\DailyDungeonFirstClearReward;
use App\Models\DailyDungeonLevel;
use App\Models\DailyDungeonLevelMonster;
use App\Models\DailyDungeonUpgradeCost;
use App\Models\Item;
use Illuminate\Validation\ValidationException;

class DailyDungeonSupport
{
    public static function levelNoOptions(): array
    {
        return [
            1 => 'Lv1',
            2 => 'Lv2',
            3 => 'Lv3',
            4 => 'Lv4',
            5 => 'Lv5',
        ];
    }

    public static function levelNameSamples(): array
    {
        return [
            1 => '初阶',
            2 => '进阶',
            3 => '精修',
            4 => '深炼',
            5 => '极境',
        ];
    }

    public static function upgradeTargetOptions(?int $levelNo): array
    {
        $levelNo = (int) ($levelNo ?? 0);

        if ($levelNo < 1 || $levelNo >= 5) {
            return [];
        }

        return [
            $levelNo + 1 => sprintf('Lv%d', $levelNo + 1),
        ];
    }

    public static function monsterEntriesForForm(DailyDungeonLevel $level, string $spawnType): array
    {
        return $level->monsterEntries()
            ->where('spawn_type', $spawnType)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DailyDungeonLevelMonster $entry): array => [
                'monster_id' => (string) $entry->monster_id,
                'weight' => (int) $entry->weight,
                'min_count' => (int) $entry->min_count,
                'max_count' => (int) $entry->max_count,
                'sort_order' => (int) $entry->sort_order,
                'is_enabled' => (bool) $entry->is_enabled,
                'remark' => $entry->remark !== null ? (string) $entry->remark : null,
            ])
            ->all();
    }

    public static function normalizeMonsterEntriesOrFail(mixed $raw, string $spawnType): array
    {
        $rows = MonsterModuleSupport::normalizeDifficultyMonsters($raw, $spawnType);
        MonsterModuleSupport::validateDifficultyMonstersOrFail($rows, $spawnType);

        return $rows;
    }

    public static function upgradeCostsForForm(DailyDungeonLevel $level): array
    {
        return $level->upgradeCosts()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DailyDungeonUpgradeCost $cost): array => [
                'target_level_no' => (int) $cost->target_level_no,
                'item_id' => (string) $cost->item_id,
                'count' => (int) $cost->count,
                'sort_order' => (int) $cost->sort_order,
                'is_enabled' => (bool) $cost->is_enabled,
                'remark' => $cost->remark !== null ? (string) $cost->remark : null,
            ])
            ->all();
    }

    public static function normalizeUpgradeCostsOrFail(mixed $raw, int $currentLevelNo): array
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
                'target_level_no' => (int) ($row['target_level_no'] ?? 0),
                'item_id' => $itemId,
                'count' => max(1, (int) ($row['count'] ?? 1)),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? ($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        static::validateUpgradeCostsOrFail($rows, $currentLevelNo);

        return array_values($rows);
    }

    public static function firstClearRewardsForForm(DailyDungeonLevel $level): array
    {
        return $level->firstClearRewards()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DailyDungeonFirstClearReward $reward): array => [
                'item_id' => (string) $reward->item_id,
                'count' => (int) $reward->count,
                'sort_order' => (int) $reward->sort_order,
                'is_enabled' => (bool) $reward->is_enabled,
                'remark' => $reward->remark !== null ? (string) $reward->remark : null,
            ])
            ->all();
    }

    public static function normalizeFirstClearRewardsOrFail(mixed $raw): array
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
                'count' => max(1, (int) ($row['count'] ?? 1)),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? ($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        static::validateFirstClearRewardsOrFail($rows);

        return array_values($rows);
    }

    public static function syncLevelRelations(
        DailyDungeonLevel $level,
        array $normalMonsters,
        array $eliteMonsters,
        array $bossMonsters,
        array $upgradeCosts,
        array $firstClearRewards,
    ): void {
        $level->monsterEntries()->delete();
        foreach (array_merge($normalMonsters, $eliteMonsters, $bossMonsters) as $row) {
            $level->monsterEntries()->create($row);
        }

        $level->upgradeCosts()->delete();
        foreach ($upgradeCosts as $row) {
            $level->upgradeCosts()->create($row);
        }

        $level->firstClearRewards()->delete();
        foreach ($firstClearRewards as $row) {
            $level->firstClearRewards()->create($row);
        }
    }

    public static function monsterSummary(DailyDungeonLevel $level): string
    {
        $counts = $level->monsterEntries()
            ->selectRaw('spawn_type, count(*) as total')
            ->groupBy('spawn_type')
            ->pluck('total', 'spawn_type')
            ->all();

        return sprintf(
            '普通%d / 精英%d / Boss%d',
            (int) ($counts['normal'] ?? 0),
            (int) ($counts['elite'] ?? 0),
            (int) ($counts['boss'] ?? 0),
        );
    }

    public static function upgradeSummary(DailyDungeonLevel $level): string
    {
        $items = $level->upgradeCosts()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DailyDungeonUpgradeCost $cost): string => sprintf('%s x%d', AdminOptions::itemName((string) $cost->item_id), (int) $cost->count))
            ->all();

        return $items === [] ? '无' : implode('、', array_slice($items, 0, 3));
    }

    public static function rewardSummary(DailyDungeonLevel $level): string
    {
        $items = $level->firstClearRewards()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DailyDungeonFirstClearReward $reward): string => sprintf('%s x%d', AdminOptions::itemName((string) $reward->item_id), (int) $reward->count))
            ->all();

        return $items === [] ? '未配置' : implode('、', array_slice($items, 0, 3));
    }

    public static function validateUpgradeCostsOrFail(array $rows, int $currentLevelNo): void
    {
        $errors = [];
        $validItemLookup = array_fill_keys(Item::query()->pluck('item_id')->all(), true);
        $expectedTarget = $currentLevelNo >= 1 && $currentLevelNo < 5 ? $currentLevelNo + 1 : null;
        $seenItems = [];

        if ($currentLevelNo >= 5 && $rows !== []) {
            $errors['upgrade_costs'] = 'Lv5 不应再配置升级消耗。';
        }

        foreach ($rows as $index => $row) {
            $itemId = trim((string) ($row['item_id'] ?? ''));
            $targetLevelNo = (int) ($row['target_level_no'] ?? 0);
            $count = (int) ($row['count'] ?? 0);

            if ($itemId === '') {
                $errors["upgrade_costs.{$index}.item_id"] = '请选择升级材料。';
            } elseif (! isset($validItemLookup[$itemId])) {
                $errors["upgrade_costs.{$index}.item_id"] = '升级材料不存在。';
            } elseif (isset($seenItems[$itemId])) {
                $errors["upgrade_costs.{$index}.item_id"] = sprintf('升级消耗存在重复材料：%s。', AdminOptions::itemName($itemId));
            } else {
                $seenItems[$itemId] = true;
            }

            if ($count < 1) {
                $errors["upgrade_costs.{$index}.count"] = '升级材料数量必须大于等于 1。';
            }

            if ($expectedTarget === null) {
                if ($targetLevelNo !== 0) {
                    $errors["upgrade_costs.{$index}.target_level_no"] = 'Lv5 不允许配置 target_level_no。';
                }
            } elseif ($targetLevelNo !== $expectedTarget) {
                $errors["upgrade_costs.{$index}.target_level_no"] = sprintf('当前等级 Lv%d 只能配置升到 Lv%d 的消耗。', $currentLevelNo, $expectedTarget);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function validateFirstClearRewardsOrFail(array $rows): void
    {
        $errors = [];
        $validItemLookup = array_fill_keys(Item::query()->pluck('item_id')->all(), true);

        foreach ($rows as $index => $row) {
            $itemId = trim((string) ($row['item_id'] ?? ''));
            $count = (int) ($row['count'] ?? 0);

            if ($itemId === '') {
                $errors["first_clear_rewards.{$index}.item_id"] = '请选择奖励物品。';
            } elseif (! isset($validItemLookup[$itemId])) {
                $errors["first_clear_rewards.{$index}.item_id"] = '奖励物品不存在。';
            }

            if ($count < 1) {
                $errors["first_clear_rewards.{$index}.count"] = '奖励数量必须大于等于 1。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
