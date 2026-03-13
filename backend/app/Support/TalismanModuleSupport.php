<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Talisman;
use App\Models\TalismanStarLink;
use App\Models\TalismanTier;
use App\Models\TalismanTierUpgradeCost;
use App\Services\ItemCatalogImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TalismanModuleSupport
{
    public const STAR_LINK_CONDITION_MODE = 'all_tracked_slots_min_star';

    public const STAR_LINK_TRACKED_SLOT_IDS = [
        'main_weapon',
        'off_weapon',
        'armor',
        'belt',
        'shoes',
        'gloves',
        'helm',
        'necklace',
        'ring',
        'bracelet',
    ];

    public const STAR_LINK_EXCLUDED_SLOT_IDS = [
        'talisman',
    ];

    public static function rulesPayload(): array
    {
        return [
            'fixed_tier_numbers' => Talisman::FIXED_TIER_NUMBERS,
            'star_link_required_equipment_stars' => Talisman::STAR_LINK_THRESHOLDS,
            'star_link_condition_mode' => self::STAR_LINK_CONDITION_MODE,
            'star_link_condition_summary' => '护符星级连锁所要求的“全身装备达到 X 星”，指所有参与统计的装备位都至少达到该星级。护符位本身不参与该统计。',
            'star_link_tracked_slot_ids' => self::STAR_LINK_TRACKED_SLOT_IDS,
            'star_link_excluded_slot_ids' => self::STAR_LINK_EXCLUDED_SLOT_IDS,
            'recommended_sect_mode' => 'soft_recommendation_only',
            'recommended_sect_summary' => 'recommended_sect 仅用于推荐展示，不做强制宗门限制。',
        ];
    }

    public static function carrierItemOptions(?string $talismanType = null): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->where('main_type', 'talisman')
            ->when(
                filled($talismanType),
                fn (Builder $query): Builder => $query->where('sub_type', $talismanType)
            )
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->mapWithKeys(fn (Item $item): array => [
                (string) $item->item_id => sprintf('%s｜%s', (string) $item->display_name, (string) $item->item_id),
            ])
            ->all();
    }

    public static function upgradeCostItemOptions(): array
    {
        return AdminOptions::itemOptions(
            fn (Builder $query): Builder => $query
                ->where('main_type', 'material')
                ->where('is_enabled', true)
        );
    }

    /**
     * @return array{talisman: array<string, mixed>, tiers: array<int, array<string, mixed>>, upgrade_costs: array<int, array<string, mixed>>, star_links: array<int, array<string, mixed>>}
     */
    public static function normalizeSingleTalismanFormOrFail(array $data): array
    {
        $talisman = static::normalizeTalismanRowOrFail($data, 'talisman');
        $knownTalismanIds = [(string) $talisman['talisman_id']];

        $tiers = static::normalizeTierRowsOrFail($data['tiers'] ?? [], $knownTalismanIds, 'tiers');
        $upgradeCosts = static::normalizeUpgradeCostRowsOrFail($data['upgrade_costs'] ?? [], $knownTalismanIds, 'upgrade_costs');
        $starLinks = static::normalizeStarLinkRowsOrFail($data['star_links'] ?? [], $knownTalismanIds, 'star_links');

        return [
            'talisman' => $talisman,
            'tiers' => $tiers,
            'upgrade_costs' => $upgradeCosts,
            'star_links' => $starLinks,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeTalismansOrFail(array $rows): array
    {
        $errors = [];
        $normalized = [];
        $seenTalismanIds = [];
        $seenItemIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["talismans.{$index}"] = '护符条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = static::normalizeTalismanRowOrFail($row, "talismans.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $talismanId = (string) $normalizedRow['talisman_id'];
            $itemId = (string) $normalizedRow['item_id'];

            if (isset($seenTalismanIds[$talismanId])) {
                $errors["talismans.{$index}.talisman_id"] = sprintf('talisman_id 重复：%s。', $talismanId);
            }

            if (isset($seenItemIds[$itemId])) {
                $errors["talismans.{$index}.item_id"] = sprintf('item_id 重复：%s。', $itemId);
            }

            $seenTalismanIds[$talismanId] = true;
            $seenItemIds[$itemId] = true;
            $normalized[] = $normalizedRow;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $knownTalismanIds
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeTierRowsOrFail(array $rows, array $knownTalismanIds, string $pathPrefix = 'talisman_tiers'): array
    {
        $errors = [];
        $normalized = [];
        $knownLookup = array_fill_keys($knownTalismanIds, true);
        $seenByTalisman = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '护符阶级条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = static::normalizeTierRowOrFail($row, $knownLookup, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $key = sprintf('%s:%s', $normalizedRow['talisman_id'], $normalizedRow['tier_no']);
            if (isset($seenByTalisman[$key])) {
                $errors["{$pathPrefix}.{$index}.tier_no"] = sprintf('护符 %s 的 tier_no=%s 重复。', $normalizedRow['talisman_id'], $normalizedRow['tier_no']);
            }

            $seenByTalisman[$key] = true;
            $normalized[] = $normalizedRow;
        }

        foreach ($knownTalismanIds as $talismanId) {
            $tierNos = Collection::make($normalized)
                ->where('talisman_id', $talismanId)
                ->pluck('tier_no')
                ->sort()
                ->values()
                ->all();

            if ($tierNos !== Talisman::FIXED_TIER_NUMBERS) {
                $errors["{$pathPrefix}.{$talismanId}"] = sprintf(
                    '护符 %s 必须且只能配置 1/2/3 三个阶级。',
                    $talismanId,
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $knownTalismanIds
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeUpgradeCostRowsOrFail(array $rows, array $knownTalismanIds, string $pathPrefix = 'talisman_tier_upgrade_costs'): array
    {
        $errors = [];
        $normalized = [];
        $knownLookup = array_fill_keys($knownTalismanIds, true);

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '护符升阶消耗条目格式错误。';
                continue;
            }

            try {
                $normalized[] = static::normalizeUpgradeCostRowOrFail($row, $knownLookup, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
            }
        }

        foreach ($knownTalismanIds as $talismanId) {
            foreach ([[1, 2], [2, 3]] as [$tierNo, $targetTierNo]) {
                $count = Collection::make($normalized)
                    ->where('talisman_id', $talismanId)
                    ->where('tier_no', $tierNo)
                    ->where('target_tier_no', $targetTierNo)
                    ->count();

                if ($count < 1) {
                    $errors["{$pathPrefix}.{$talismanId}.{$tierNo}_{$targetTierNo}"] = sprintf(
                        '护符 %s 必须配置 %d -> %d 的升阶消耗。',
                        $talismanId,
                        $tierNo,
                        $targetTierNo,
                    );
                }
            }

            $invalidTierThreeCosts = Collection::make($normalized)
                ->where('talisman_id', $talismanId)
                ->where('tier_no', 3)
                ->count();

            if ($invalidTierThreeCosts > 0) {
                $errors["{$pathPrefix}.{$talismanId}.tier_3"] = sprintf('护符 %s 的 3 阶不能再配置升级消耗。', $talismanId);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $knownTalismanIds
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeStarLinkRowsOrFail(array $rows, array $knownTalismanIds, string $pathPrefix = 'talisman_star_links'): array
    {
        $errors = [];
        $normalized = [];
        $knownLookup = array_fill_keys($knownTalismanIds, true);
        $seenKeys = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '护符星级连锁条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = static::normalizeStarLinkRowOrFail($row, $knownLookup, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $key = sprintf(
                '%s:%s:%s',
                $normalizedRow['talisman_id'],
                $normalizedRow['tier_no'],
                $normalizedRow['required_equipment_star'],
            );

            if (isset($seenKeys[$key])) {
                $errors["{$pathPrefix}.{$index}.required_equipment_star"] = sprintf(
                    '护符 %s 的 tier=%s、required_equipment_star=%s 重复。',
                    $normalizedRow['talisman_id'],
                    $normalizedRow['tier_no'],
                    $normalizedRow['required_equipment_star'],
                );
            }

            $seenKeys[$key] = true;
            $normalized[] = $normalizedRow;
        }

        foreach ($knownTalismanIds as $talismanId) {
            foreach (Talisman::FIXED_TIER_NUMBERS as $tierNo) {
                $thresholds = Collection::make($normalized)
                    ->where('talisman_id', $talismanId)
                    ->where('tier_no', $tierNo)
                    ->pluck('required_equipment_star')
                    ->sort()
                    ->values()
                    ->all();

                if ($thresholds !== Talisman::STAR_LINK_THRESHOLDS) {
                    $errors["{$pathPrefix}.{$talismanId}.tier_{$tierNo}"] = sprintf(
                        '护符 %s 的 %d 阶必须完整配置 6/8/9/10 星连锁。',
                        $talismanId,
                        $tierNo,
                    );
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, bool>  $knownTalismanIds
     * @return array<string, mixed>
     */
    public static function normalizeTalismanRowOrFail(array $row, string $pathPrefix = 'talisman'): array
    {
        $errors = [];

        $talismanId = trim((string) ($row['talisman_id'] ?? ''));
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $talismanName = trim((string) ($row['talisman_name'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        $talismanType = trim((string) ($row['talisman_type'] ?? ''));
        $recommendedSect = trim((string) ($row['recommended_sect'] ?? 'none'));
        $quality = ItemCatalogImportService::normalizeTier((string) ($row['quality'] ?? 'white'));
        $rarity = ItemCatalogImportService::normalizeTier((string) ($row['rarity'] ?? 'white'));
        $unlockLevel = max(1, (int) ($row['unlock_level'] ?? 1));
        $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));
        $icon = filled($row['icon'] ?? null) ? trim((string) $row['icon']) : null;
        $summary = filled($row['summary'] ?? null) ? trim((string) $row['summary']) : null;
        $remark = filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null;
        $isEnabled = (bool) ($row['is_enabled'] ?? true);

        if ($talismanId === '') {
            $errors["{$pathPrefix}.talisman_id"] = '请填写 talisman_id。';
        }

        if ($itemId === '') {
            $errors["{$pathPrefix}.item_id"] = '请填写 item_id。';
        }

        if ($talismanName === '') {
            $errors["{$pathPrefix}.talisman_name"] = '请填写护符内部名称。';
        }

        if ($displayName === '') {
            $errors["{$pathPrefix}.display_name"] = '请填写护符展示名称。';
        }

        if (! array_key_exists($talismanType, Talisman::TALISMAN_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.talisman_type"] = 'talisman_type 非法，只支持 common_talisman / sect_talisman。';
        }

        if (! array_key_exists($recommendedSect, Talisman::RECOMMENDED_SECT_OPTIONS)) {
            $errors["{$pathPrefix}.recommended_sect"] = 'recommended_sect 非法。';
        }

        if (! array_key_exists($quality, Item::QUALITY_OPTIONS)) {
            $errors["{$pathPrefix}.quality"] = 'quality 非法。';
        }

        if (! array_key_exists($rarity, Item::RARITY_OPTIONS)) {
            $errors["{$pathPrefix}.rarity"] = 'rarity 非法。';
        }

        $item = $itemId !== ''
            ? Item::query()->where('item_id', $itemId)->first()
            : null;

        if ($itemId !== '' && ! $item instanceof Item) {
            $errors["{$pathPrefix}.item_id"] = 'item_id 必须命中已存在的 items.item_id。';
        } elseif ($item instanceof Item) {
            if ((string) $item->main_type !== 'talisman') {
                $errors["{$pathPrefix}.item_id"] = 'item_id 必须引用 main_type = talisman 的物品。';
            }

            if (filled($talismanType) && (string) ($item->sub_type ?? '') !== $talismanType) {
                $errors["{$pathPrefix}.item_id"] = 'item_id 对应 item.sub_type 必须与 talisman_type 一致。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'talisman_id' => $talismanId,
            'item_id' => $itemId,
            'talisman_name' => $talismanName,
            'display_name' => $displayName,
            'talisman_type' => $talismanType,
            'recommended_sect' => $recommendedSect,
            'quality' => $quality,
            'rarity' => $rarity,
            'unlock_level' => $unlockLevel,
            'icon' => $icon,
            'summary' => $summary,
            'sort_order' => $sortOrder,
            'is_enabled' => $isEnabled,
            'remark' => $remark,
        ];
    }

    /**
     * @param  array<string, bool>  $knownTalismanIds
     * @return array<string, mixed>
     */
    public static function normalizeTierRowOrFail(array $row, array $knownTalismanIds, string $pathPrefix): array
    {
        $errors = [];

        $talismanId = trim((string) ($row['talisman_id'] ?? ''));
        $tierNo = (int) ($row['tier_no'] ?? 0);
        $tierName = trim((string) ($row['tier_name'] ?? ''));
        $effectKey = trim((string) ($row['effect_key'] ?? ''));
        $valueType = trim((string) ($row['value_type'] ?? ''));
        $triggerRule = trim((string) ($row['trigger_rule'] ?? ''));
        $cooldownSec = max(0, (int) ($row['cooldown_sec'] ?? 0));
        $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));
        $summary = filled($row['summary'] ?? null) ? trim((string) $row['summary']) : null;
        $remark = filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null;
        $isEnabled = (bool) ($row['is_enabled'] ?? true);

        if ($talismanId === '' || ! isset($knownTalismanIds[$talismanId])) {
            $errors["{$pathPrefix}.talisman_id"] = 'talisman_id 必须命中已定义的护符。';
        }

        if (! in_array($tierNo, Talisman::FIXED_TIER_NUMBERS, true)) {
            $errors["{$pathPrefix}.tier_no"] = 'tier_no 非法，只支持 1 / 2 / 3。';
        }

        if ($tierName === '') {
            $errors["{$pathPrefix}.tier_name"] = '请填写阶级名称。';
        }

        if (! array_key_exists($effectKey, Talisman::EFFECT_KEY_OPTIONS)) {
            $errors["{$pathPrefix}.effect_key"] = 'effect_key 非法。';
        }

        if (! array_key_exists($valueType, Talisman::VALUE_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.value_type"] = 'value_type 非法。';
        }

        if (! is_numeric($row['value'] ?? null)) {
            $errors["{$pathPrefix}.value"] = 'value 必须为数值。';
        }

        if (! array_key_exists($triggerRule, TalismanTier::TRIGGER_RULE_OPTIONS)) {
            $errors["{$pathPrefix}.trigger_rule"] = 'trigger_rule 非法。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'talisman_id' => $talismanId,
            'tier_no' => $tierNo,
            'tier_name' => $tierName,
            'effect_key' => $effectKey,
            'value_type' => $valueType,
            'value' => static::normalizeNumericValue((float) $row['value']),
            'trigger_rule' => $triggerRule,
            'cooldown_sec' => $cooldownSec,
            'summary' => $summary,
            'sort_order' => $sortOrder,
            'is_enabled' => $isEnabled,
            'remark' => $remark,
        ];
    }

    /**
     * @param  array<string, bool>  $knownTalismanIds
     * @return array<string, mixed>
     */
    public static function normalizeUpgradeCostRowOrFail(array $row, array $knownTalismanIds, string $pathPrefix): array
    {
        $errors = [];

        $talismanId = trim((string) ($row['talisman_id'] ?? ''));
        $tierNo = (int) ($row['tier_no'] ?? 0);
        $targetTierNo = (int) ($row['target_tier_no'] ?? 0);
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $count = max(0, (int) ($row['count'] ?? 0));
        $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));
        $remark = filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null;
        $isEnabled = (bool) ($row['is_enabled'] ?? true);

        if ($talismanId === '' || ! isset($knownTalismanIds[$talismanId])) {
            $errors["{$pathPrefix}.talisman_id"] = 'talisman_id 必须命中已定义的护符。';
        }

        if (! in_array([$tierNo, $targetTierNo], [[1, 2], [2, 3]], true)) {
            $errors["{$pathPrefix}.target_tier_no"] = '升阶消耗只允许 1->2 或 2->3。';
        }

        if ($itemId === '') {
            $errors["{$pathPrefix}.item_id"] = '请填写消耗物品 item_id。';
        }

        if ($count < 1) {
            $errors["{$pathPrefix}.count"] = 'count 必须大于 0。';
        }

        $item = $itemId !== ''
            ? Item::query()->where('item_id', $itemId)->first()
            : null;

        if ($itemId !== '' && ! $item instanceof Item) {
            $errors["{$pathPrefix}.item_id"] = 'item_id 必须命中已存在的 items.item_id。';
        } elseif ($item instanceof Item && (string) $item->main_type !== 'material') {
            $errors["{$pathPrefix}.item_id"] = '升阶消耗 item_id 当前必须引用 material 类型物品。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'talisman_id' => $talismanId,
            'tier_no' => $tierNo,
            'target_tier_no' => $targetTierNo,
            'item_id' => $itemId,
            'count' => $count,
            'sort_order' => $sortOrder,
            'is_enabled' => $isEnabled,
            'remark' => $remark,
        ];
    }

    /**
     * @param  array<string, bool>  $knownTalismanIds
     * @return array<string, mixed>
     */
    public static function normalizeStarLinkRowOrFail(array $row, array $knownTalismanIds, string $pathPrefix): array
    {
        $errors = [];

        $talismanId = trim((string) ($row['talisman_id'] ?? ''));
        $tierNo = (int) ($row['tier_no'] ?? 0);
        $requiredEquipmentStar = (int) ($row['required_equipment_star'] ?? 0);
        $effectKey = trim((string) ($row['effect_key'] ?? ''));
        $valueType = trim((string) ($row['value_type'] ?? ''));
        $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));
        $summary = filled($row['summary'] ?? null) ? trim((string) $row['summary']) : null;
        $remark = filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null;
        $isEnabled = (bool) ($row['is_enabled'] ?? true);

        if ($talismanId === '' || ! isset($knownTalismanIds[$talismanId])) {
            $errors["{$pathPrefix}.talisman_id"] = 'talisman_id 必须命中已定义的护符。';
        }

        if (! in_array($tierNo, Talisman::FIXED_TIER_NUMBERS, true)) {
            $errors["{$pathPrefix}.tier_no"] = 'tier_no 非法，只支持 1 / 2 / 3。';
        }

        if (! in_array($requiredEquipmentStar, Talisman::STAR_LINK_THRESHOLDS, true)) {
            $errors["{$pathPrefix}.required_equipment_star"] = 'required_equipment_star 非法，只支持 6 / 8 / 9 / 10。';
        }

        if (! array_key_exists($effectKey, Talisman::EFFECT_KEY_OPTIONS)) {
            $errors["{$pathPrefix}.effect_key"] = 'effect_key 非法。';
        }

        if (! array_key_exists($valueType, Talisman::VALUE_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.value_type"] = 'value_type 非法。';
        }

        if (! is_numeric($row['value'] ?? null)) {
            $errors["{$pathPrefix}.value"] = 'value 必须为数值。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'talisman_id' => $talismanId,
            'tier_no' => $tierNo,
            'required_equipment_star' => $requiredEquipmentStar,
            'effect_key' => $effectKey,
            'value_type' => $valueType,
            'value' => static::normalizeNumericValue((float) $row['value']),
            'summary' => $summary,
            'sort_order' => $sortOrder,
            'is_enabled' => $isEnabled,
            'remark' => $remark,
        ];
    }

    public static function exportTalisman(Talisman $talisman): array
    {
        return [
            'talisman_id' => (string) $talisman->talisman_id,
            'item_id' => (string) $talisman->item_id,
            'talisman_name' => (string) $talisman->talisman_name,
            'display_name' => (string) $talisman->display_name,
            'talisman_type' => (string) $talisman->talisman_type,
            'recommended_sect' => (string) $talisman->recommended_sect,
            'quality' => (string) $talisman->quality,
            'rarity' => (string) $talisman->rarity,
            'unlock_level' => (int) $talisman->unlock_level,
            'icon' => (string) ($talisman->icon ?? ''),
            'summary' => (string) ($talisman->summary ?? ''),
            'sort_order' => (int) $talisman->sort_order,
            'is_enabled' => (bool) $talisman->is_enabled,
            'remark' => (string) ($talisman->remark ?? ''),
        ];
    }

    public static function exportTier(TalismanTier $tier): array
    {
        return [
            'talisman_id' => (string) $tier->talisman_id,
            'tier_no' => (int) $tier->tier_no,
            'tier_name' => (string) $tier->tier_name,
            'effect_key' => (string) $tier->effect_key,
            'value_type' => (string) $tier->value_type,
            'value' => static::normalizeNumericValue((float) $tier->value),
            'trigger_rule' => (string) $tier->trigger_rule,
            'cooldown_sec' => (int) $tier->cooldown_sec,
            'summary' => (string) ($tier->summary ?? ''),
            'sort_order' => (int) $tier->sort_order,
            'is_enabled' => (bool) $tier->is_enabled,
            'remark' => (string) ($tier->remark ?? ''),
        ];
    }

    public static function exportUpgradeCost(TalismanTierUpgradeCost $cost): array
    {
        return [
            'talisman_id' => (string) $cost->talisman_id,
            'tier_no' => (int) $cost->tier_no,
            'target_tier_no' => (int) $cost->target_tier_no,
            'item_id' => (string) $cost->item_id,
            'count' => (int) $cost->count,
            'sort_order' => (int) $cost->sort_order,
            'is_enabled' => (bool) $cost->is_enabled,
            'remark' => (string) ($cost->remark ?? ''),
        ];
    }

    public static function exportStarLink(TalismanStarLink $link): array
    {
        return [
            'talisman_id' => (string) $link->talisman_id,
            'tier_no' => (int) $link->tier_no,
            'required_equipment_star' => (int) $link->required_equipment_star,
            'effect_key' => (string) $link->effect_key,
            'value_type' => (string) $link->value_type,
            'value' => static::normalizeNumericValue((float) $link->value),
            'summary' => (string) ($link->summary ?? ''),
            'sort_order' => (int) $link->sort_order,
            'is_enabled' => (bool) $link->is_enabled,
            'remark' => (string) ($link->remark ?? ''),
        ];
    }

    public static function normalizeNumericValue(float $value): int|float
    {
        $normalized = round($value, 4);

        if (abs($normalized - round($normalized)) < 0.0001) {
            return (int) round($normalized);
        }

        return $normalized;
    }
}
