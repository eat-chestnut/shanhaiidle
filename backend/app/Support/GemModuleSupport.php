<?php

namespace App\Support;

use App\Models\Gem;
use App\Models\Item;
use App\Services\ItemCatalogImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class GemModuleSupport
{
    public static function statKeyOptions(?string $gemType = null): array
    {
        return match ($gemType) {
            'attr_gem' => Gem::ATTR_STAT_KEY_OPTIONS,
            'skill_gem' => Gem::SKILL_STAT_KEY_OPTIONS,
            default => Gem::ATTR_STAT_KEY_OPTIONS + Gem::SKILL_STAT_KEY_OPTIONS,
        };
    }

    public static function defaultSlotGroupForGemType(?string $gemType): ?string
    {
        return match ($gemType) {
            'attr_gem' => 'attr_only',
            'skill_gem' => 'skill_only',
            default => null,
        };
    }

    public static function itemOptions(?string $gemType = null): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->where('main_type', 'gem')
            ->when(
                filled($gemType),
                fn (Builder $query): Builder => $query->where('sub_type', $gemType)
            )
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->mapWithKeys(fn (Item $item): array => [
                (string) $item->item_id => sprintf('%s｜%s', (string) $item->display_name, (string) $item->item_id),
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRowsOrFail(array $rows): array
    {
        $errors = [];
        $normalized = [];
        $seenGemIds = [];
        $seenItemIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["gem_catalog.{$index}"] = '宝石条目格式错误。';

                continue;
            }

            try {
                $normalizedRow = static::normalizeRowOrFail($row, "gem_catalog.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());

                continue;
            }

            $gemId = (string) $normalizedRow['gem_id'];
            $itemId = (string) $normalizedRow['item_id'];

            if (isset($seenGemIds[$gemId])) {
                $errors["gem_catalog.{$index}.gem_id"] = sprintf('gem_id 重复：%s。', $gemId);
            }

            if (isset($seenItemIds[$itemId])) {
                $errors["gem_catalog.{$index}.item_id"] = sprintf('item_id 重复：%s。', $itemId);
            }

            $seenGemIds[$gemId] = true;
            $seenItemIds[$itemId] = true;
            $normalized[] = $normalizedRow;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeRowOrFail(array $row, string $pathPrefix = 'gem'): array
    {
        $errors = [];

        $gemId = trim((string) ($row['gem_id'] ?? ''));
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $gemName = trim((string) ($row['gem_name'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        $gemType = trim((string) ($row['gem_type'] ?? ''));
        $statKey = trim((string) ($row['stat_key'] ?? ''));
        $valueType = trim((string) ($row['value_type'] ?? ''));
        $qualityValue = ItemCatalogImportService::normalizeTier((string) ($row['quality'] ?? 'white'));
        $rarityValue = ItemCatalogImportService::normalizeTier((string) ($row['rarity'] ?? 'white'));
        $slotGroup = trim((string) ($row['slot_group'] ?? ''));
        $unlockLevel = max(1, (int) ($row['unlock_level'] ?? 1));
        $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));
        $icon = filled($row['icon'] ?? null) ? trim((string) $row['icon']) : null;
        $summary = filled($row['summary'] ?? null) ? trim((string) $row['summary']) : null;
        $remark = filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null;
        $isEnabled = (bool) ($row['is_enabled'] ?? true);

        if ($gemId === '') {
            $errors["{$pathPrefix}.gem_id"] = '请填写 gem_id。';
        }

        if ($itemId === '') {
            $errors["{$pathPrefix}.item_id"] = '请填写 item_id。';
        }

        if ($gemName === '') {
            $errors["{$pathPrefix}.gem_name"] = '请填写宝石内部名称。';
        }

        if ($displayName === '') {
            $errors["{$pathPrefix}.display_name"] = '请填写宝石展示名称。';
        }

        if (! array_key_exists($gemType, Gem::GEM_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.gem_type"] = 'gem_type 非法，只支持 attr_gem / skill_gem。';
        }

        if (! array_key_exists($valueType, Gem::VALUE_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.value_type"] = 'value_type 非法，只支持 flat / percent。';
        }

        if (! array_key_exists($qualityValue, Item::QUALITY_OPTIONS)) {
            $errors["{$pathPrefix}.quality"] = 'quality 非法。';
        }

        if (! array_key_exists($rarityValue, Item::RARITY_OPTIONS)) {
            $errors["{$pathPrefix}.rarity"] = 'rarity 非法。';
        }

        if (! array_key_exists($slotGroup, Gem::SLOT_GROUP_OPTIONS)) {
            $errors["{$pathPrefix}.slot_group"] = 'slot_group 非法，只支持 attr_only / skill_only。';
        }

        $expectedSlotGroup = static::defaultSlotGroupForGemType($gemType);
        if ($expectedSlotGroup !== null && $slotGroup !== $expectedSlotGroup) {
            $errors["{$pathPrefix}.slot_group"] = sprintf('gem_type=%s 时 slot_group 必须为 %s。', $gemType, $expectedSlotGroup);
        }

        if ($statKey === '' || ! array_key_exists($statKey, static::statKeyOptions($gemType))) {
            $errors["{$pathPrefix}.stat_key"] = 'stat_key 非法，必须匹配当前 gem_type 的正式 key。';
        }

        if (! is_numeric($row['value'] ?? null)) {
            $errors["{$pathPrefix}.value"] = 'value 必须为数值。';
        }

        $item = $itemId !== ''
            ? Item::query()->where('item_id', $itemId)->first()
            : null;

        if ($itemId !== '' && ! $item instanceof Item) {
            $errors["{$pathPrefix}.item_id"] = 'item_id 必须命中已存在的 items.item_id。';
        } elseif ($item instanceof Item) {
            if ((string) $item->main_type !== 'gem') {
                $errors["{$pathPrefix}.item_id"] = 'item_id 必须引用 main_type = gem 的物品。';
            }

            if (filled($gemType) && (string) ($item->sub_type ?? '') !== $gemType) {
                $errors["{$pathPrefix}.item_id"] = 'item_id 对应 item.sub_type 必须与 gem_type 一致。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'gem_id' => $gemId,
            'item_id' => $itemId,
            'gem_name' => $gemName,
            'display_name' => $displayName,
            'gem_type' => $gemType,
            'stat_key' => $statKey,
            'value_type' => $valueType,
            'value' => static::normalizeNumericValue((float) $row['value']),
            'quality' => $qualityValue,
            'rarity' => $rarityValue,
            'slot_group' => $slotGroup,
            'unlock_level' => $unlockLevel,
            'icon' => $icon,
            'summary' => $summary,
            'sort_order' => $sortOrder,
            'is_enabled' => $isEnabled,
            'remark' => $remark,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportRow(Gem $gem): array
    {
        return [
            'gem_id' => (string) $gem->gem_id,
            'item_id' => (string) $gem->item_id,
            'gem_name' => (string) $gem->gem_name,
            'display_name' => (string) $gem->display_name,
            'gem_type' => (string) $gem->gem_type,
            'stat_key' => (string) $gem->stat_key,
            'value_type' => (string) $gem->value_type,
            'value' => static::normalizeNumericValue((float) $gem->value),
            'quality' => (string) $gem->quality,
            'rarity' => (string) $gem->rarity,
            'slot_group' => (string) $gem->slot_group,
            'unlock_level' => (int) $gem->unlock_level,
            'icon' => (string) ($gem->icon ?? ''),
            'summary' => (string) ($gem->summary ?? ''),
            'sort_order' => (int) $gem->sort_order,
            'is_enabled' => (bool) $gem->is_enabled,
            'remark' => (string) ($gem->remark ?? ''),
        ];
    }

    /**
     * @return int|float
     */
    public static function normalizeNumericValue(float $value): int|float
    {
        $normalized = round($value, 4);

        if (abs($normalized - round($normalized)) < 0.0001) {
            return (int) round($normalized);
        }

        return $normalized;
    }
}
