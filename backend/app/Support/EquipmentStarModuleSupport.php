<?php

namespace App\Support;

use App\Models\EquipmentStageProgressionRule;
use App\Models\EquipmentStarRule;
use App\Models\EquipmentStarSlotUnlock;
use App\Models\EquipmentStarUpgradeCost;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EquipmentStarModuleSupport
{
    public const VERSION = 'v1';

    public const MODULE = 'equipment_star_module';

    public static function moduleRulesPayload(): array
    {
        return [
            'star_mode' => 'always_success',
            'failure_supported' => false,
            'downgrade_supported' => false,
            'guarantee_supported' => false,
            'progression_requires_max_star' => true,
            'progression_keeps_current_star' => true,
            'core_material_source' => 'star_sand_dungeon',
        ];
    }

    public static function costItemOptions(): array
    {
        return AdminOptions::itemOptions(
            fn (Builder $query): Builder => $query->whereIn('main_type', ['material', 'currency'])
        );
    }

    /**
     * @return array{
     *     rules: array<string, mixed>,
     *     star_rules: array<int, array<string, mixed>>,
     *     slot_unlocks: array<int, array<string, mixed>>,
     *     progression_rules: array<int, array<string, mixed>>,
     *     upgrade_costs: array<int, array<string, mixed>>
     * }
     */
    public static function normalizeProjectPayloadOrFail(array $decoded): array
    {
        $errors = [];

        $version = trim((string) ($decoded['version'] ?? ''));
        if ($version !== self::VERSION) {
            $errors['version'] = sprintf('version 必须为 %s。', self::VERSION);
        }

        $module = trim((string) ($decoded['module'] ?? ''));
        if ($module !== self::MODULE) {
            $errors['module'] = sprintf('module 必须为 %s。', self::MODULE);
        }

        $rules = $decoded['rules'] ?? null;
        if (! is_array($rules)) {
            $errors['rules'] = 'rules 节点缺失。';
        }

        $starRules = $decoded['star_rules'] ?? null;
        if (! is_array($starRules)) {
            $errors['star_rules'] = 'star_rules 节点缺失。';
        }

        $slotUnlocks = $decoded['slot_unlocks'] ?? null;
        if (! is_array($slotUnlocks)) {
            $errors['slot_unlocks'] = 'slot_unlocks 节点缺失。';
        }

        $progressionRules = $decoded['progression_rules'] ?? null;
        if (! is_array($progressionRules)) {
            $errors['progression_rules'] = 'progression_rules 节点缺失。';
        }

        $upgradeCosts = $decoded['upgrade_costs'] ?? null;
        if (! is_array($upgradeCosts)) {
            $errors['upgrade_costs'] = 'upgrade_costs 节点缺失。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'rules' => static::normalizeModuleRulesOrFail($rules),
            'star_rules' => static::normalizeStarRuleRowsOrFail($starRules),
            'slot_unlocks' => static::normalizeSlotUnlockRowsOrFail($slotUnlocks),
            'progression_rules' => static::normalizeProgressionRuleRowsOrFail($progressionRules),
            'upgrade_costs' => static::normalizeUpgradeCostGroupsOrFail($upgradeCosts),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function normalizeModuleRulesOrFail(array $rules, string $pathPrefix = 'rules'): array
    {
        $errors = [];
        $expected = static::moduleRulesPayload();

        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $rules)) {
                $errors["{$pathPrefix}.{$key}"] = sprintf('%s 缺失。', $key);
                continue;
            }

            if ($rules[$key] !== $value) {
                $errors["{$pathPrefix}.{$key}"] = sprintf('%s 必须固定为 %s。', $key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $expected;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeStarRuleRowsOrFail(array $rows, string $pathPrefix = 'star_rules'): array
    {
        $errors = [];
        $normalized = [];
        $seenSetLevels = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '升星规则条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = static::normalizeStarRuleRowOrFail($row, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $setLevel = (int) $normalizedRow['set_level'];
            if (isset($seenSetLevels[$setLevel])) {
                $errors["{$pathPrefix}.{$index}.set_level"] = sprintf('set_level=%d 重复。', $setLevel);
            }

            $seenSetLevels[$setLevel] = true;
            $normalized[] = $normalizedRow;
        }

        foreach (array_keys(EquipmentStarRule::MAX_STAR_BY_SET_LEVEL) as $setLevel) {
            if (! isset($seenSetLevels[$setLevel])) {
                $errors["{$pathPrefix}.{$setLevel}"] = sprintf('缺少 set_level=%d 的升星规则。', $setLevel);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort(
            $normalized,
            fn (array $left, array $right): int => [$left['sort_order'], $left['set_level']] <=> [$right['sort_order'], $right['set_level']]
        );

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeStarRuleRowOrFail(array $row, string $pathPrefix = 'equipment_star_rules'): array
    {
        $errors = [];

        $setLevel = (int) ($row['set_level'] ?? 0);
        $maxStar = (int) ($row['max_star'] ?? 0);

        if (! array_key_exists($setLevel, EquipmentStarRule::MAX_STAR_BY_SET_LEVEL)) {
            $errors["{$pathPrefix}.set_level"] = 'set_level 只允许 20 / 40 / 50 / 60。';
        } elseif ($maxStar !== EquipmentStarRule::MAX_STAR_BY_SET_LEVEL[$setLevel]) {
            $errors["{$pathPrefix}.max_star"] = sprintf(
                'set_level=%d 时 max_star 必须为 %d。',
                $setLevel,
                EquipmentStarRule::MAX_STAR_BY_SET_LEVEL[$setLevel],
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'set_level' => $setLevel,
            'max_star' => $maxStar,
            'summary' => static::nullableString($row['summary'] ?? null),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => static::nullableString($row['remark'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeSlotUnlockRowsOrFail(array $rows, string $pathPrefix = 'slot_unlocks'): array
    {
        $errors = [];
        $normalized = [];
        $seenRequiredStars = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '孔位解锁条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = static::normalizeSlotUnlockRowOrFail($row, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $requiredStar = (int) $normalizedRow['required_star'];
            if (isset($seenRequiredStars[$requiredStar])) {
                $errors["{$pathPrefix}.{$index}.required_star"] = sprintf('required_star=%d 重复。', $requiredStar);
            }

            $seenRequiredStars[$requiredStar] = true;
            $normalized[] = $normalizedRow;
        }

        foreach (array_keys(EquipmentStarSlotUnlock::STAR_SLOT_MAP) as $requiredStar) {
            if (! isset($seenRequiredStars[$requiredStar])) {
                $errors["{$pathPrefix}.{$requiredStar}"] = sprintf('缺少 required_star=%d 的孔位解锁规则。', $requiredStar);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort(
            $normalized,
            fn (array $left, array $right): int => [$left['sort_order'], $left['required_star']] <=> [$right['sort_order'], $right['required_star']]
        );

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeSlotUnlockRowOrFail(array $row, string $pathPrefix = 'equipment_star_slot_unlocks'): array
    {
        $errors = [];

        $requiredStar = (int) ($row['required_star'] ?? 0);
        $slotIndex = (int) ($row['slot_index'] ?? 0);
        $slotGroup = trim((string) ($row['slot_group'] ?? ''));
        $expected = EquipmentStarSlotUnlock::STAR_SLOT_MAP[$requiredStar] ?? null;

        if (! is_array($expected)) {
            $errors["{$pathPrefix}.required_star"] = 'required_star 只允许 3 / 6 / 8 / 10。';
        } else {
            if ($slotIndex !== (int) $expected['slot_index']) {
                $errors["{$pathPrefix}.slot_index"] = sprintf(
                    'required_star=%d 时 slot_index 必须为 %d。',
                    $requiredStar,
                    $expected['slot_index'],
                );
            }

            if ($slotGroup !== (string) $expected['slot_group']) {
                $errors["{$pathPrefix}.slot_group"] = sprintf(
                    'required_star=%d 时 slot_group 必须为 %s。',
                    $requiredStar,
                    $expected['slot_group'],
                );
            }
        }

        if (! array_key_exists($slotGroup, EquipmentStarSlotUnlock::SLOT_GROUP_OPTIONS)) {
            $errors["{$pathPrefix}.slot_group"] = 'slot_group 只允许 attr_only / skill_only。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'required_star' => $requiredStar,
            'slot_index' => $slotIndex,
            'slot_group' => $slotGroup,
            'summary' => static::nullableString($row['summary'] ?? null),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => static::nullableString($row['remark'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeProgressionRuleRowsOrFail(array $rows, string $pathPrefix = 'progression_rules'): array
    {
        $errors = [];
        $normalized = [];
        $seenFromSetLevels = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '档位进阶条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = static::normalizeProgressionRuleRowOrFail($row, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $fromSetLevel = (int) $normalizedRow['from_set_level'];
            if (isset($seenFromSetLevels[$fromSetLevel])) {
                $errors["{$pathPrefix}.{$index}.from_set_level"] = sprintf('from_set_level=%d 重复。', $fromSetLevel);
            }

            $seenFromSetLevels[$fromSetLevel] = true;
            $normalized[] = $normalizedRow;
        }

        foreach (array_keys(EquipmentStageProgressionRule::PROGRESSION_MAP) as $fromSetLevel) {
            if (! isset($seenFromSetLevels[$fromSetLevel])) {
                $errors["{$pathPrefix}.{$fromSetLevel}"] = sprintf('缺少 from_set_level=%d 的进阶规则。', $fromSetLevel);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort(
            $normalized,
            fn (array $left, array $right): int => [$left['sort_order'], $left['from_set_level']] <=> [$right['sort_order'], $right['from_set_level']]
        );

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeProgressionRuleRowOrFail(array $row, string $pathPrefix = 'equipment_stage_progression_rules'): array
    {
        $errors = [];

        $fromSetLevel = (int) ($row['from_set_level'] ?? 0);
        $toSetLevel = (int) ($row['to_set_level'] ?? 0);
        $requiredMaxStar = (int) ($row['required_max_star'] ?? 0);
        $starKeepMode = trim((string) ($row['star_keep_mode'] ?? ''));
        $expected = EquipmentStageProgressionRule::PROGRESSION_MAP[$fromSetLevel] ?? null;

        if (! is_array($expected)) {
            $errors["{$pathPrefix}.from_set_level"] = '只允许 20->40 / 40->50 / 50->60 三档进阶。';
        } else {
            if ($toSetLevel !== (int) $expected['to_set_level']) {
                $errors["{$pathPrefix}.to_set_level"] = sprintf(
                    'from_set_level=%d 时 to_set_level 必须为 %d。',
                    $fromSetLevel,
                    $expected['to_set_level'],
                );
            }

            if ($requiredMaxStar !== (int) $expected['required_max_star']) {
                $errors["{$pathPrefix}.required_max_star"] = sprintf(
                    'from_set_level=%d 时 required_max_star 必须为 %d。',
                    $fromSetLevel,
                    $expected['required_max_star'],
                );
            }
        }

        if ($starKeepMode !== 'keep_current_star') {
            $errors["{$pathPrefix}.star_keep_mode"] = 'star_keep_mode 只能为 keep_current_star。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'from_set_level' => $fromSetLevel,
            'to_set_level' => $toSetLevel,
            'required_max_star' => $requiredMaxStar,
            'star_keep_mode' => $starKeepMode,
            'summary' => static::nullableString($row['summary'] ?? null),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => static::nullableString($row['remark'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeUpgradeCostGroupsOrFail(array $rows, string $pathPrefix = 'upgrade_costs'): array
    {
        $errors = [];
        $normalized = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '升星消耗条目格式错误。';
                continue;
            }

            $setLevel = (int) ($row['set_level'] ?? 0);
            $fromStar = (int) ($row['from_star'] ?? -1);
            $toStar = (int) ($row['to_star'] ?? -1);
            $maxStar = EquipmentStarRule::MAX_STAR_BY_SET_LEVEL[$setLevel] ?? null;
            $groupHasError = false;

            if (! array_key_exists($setLevel, EquipmentStarRule::MAX_STAR_BY_SET_LEVEL)) {
                $errors["{$pathPrefix}.{$index}.set_level"] = 'set_level 只允许 20 / 40 / 50 / 60。';
                $groupHasError = true;
            }

            if ($fromStar < 0) {
                $errors["{$pathPrefix}.{$index}.from_star"] = 'from_star 必须 >= 0。';
                $groupHasError = true;
            }

            if ($toStar !== $fromStar + 1) {
                $errors["{$pathPrefix}.{$index}.to_star"] = 'to_star 必须等于 from_star + 1。';
                $groupHasError = true;
            }

            if ($maxStar !== null && $toStar > $maxStar) {
                $errors["{$pathPrefix}.{$index}.to_star"] = sprintf('set_level=%d 时 to_star 不能超过 %d。', $setLevel, $maxStar);
                $groupHasError = true;
            }

            $costItems = $row['cost_items'] ?? null;
            if (! is_array($costItems) || $costItems === []) {
                $errors["{$pathPrefix}.{$index}.cost_items"] = 'cost_items 必须是非空数组。';
                $groupHasError = true;
            }

            if ($groupHasError) {
                continue;
            }

            foreach ($costItems as $costIndex => $costItem) {
                if (! is_array($costItem)) {
                    $errors["{$pathPrefix}.{$index}.cost_items.{$costIndex}"] = 'cost_items 条目格式错误。';
                    continue;
                }

                $rowForValidation = [
                    'set_level' => $setLevel,
                    'from_star' => $fromStar,
                    'to_star' => $toStar,
                    'item_id' => $costItem['item_id'] ?? null,
                    'count' => $costItem['count'] ?? null,
                    'sort_order' => $costItem['sort_order'] ?? 0,
                    'is_enabled' => $costItem['is_enabled'] ?? true,
                    'remark' => $costItem['remark'] ?? null,
                ];

                try {
                    $normalized[] = static::normalizeUpgradeCostRowOrFail(
                        $rowForValidation,
                        "{$pathPrefix}.{$index}.cost_items.{$costIndex}"
                    );
                } catch (ValidationException $e) {
                    $errors = array_merge($errors, $e->errors());
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort(
            $normalized,
            fn (array $left, array $right): int => [
                $left['set_level'],
                $left['from_star'],
                $left['to_star'],
                $left['sort_order'],
                $left['item_id'],
            ] <=> [
                $right['set_level'],
                $right['from_star'],
                $right['to_star'],
                $right['sort_order'],
                $right['item_id'],
            ]
        );

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeUpgradeCostRowsOrFail(array $rows, string $pathPrefix = 'equipment_star_upgrade_costs'): array
    {
        $errors = [];
        $normalized = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '升星消耗记录格式错误。';
                continue;
            }

            try {
                $normalized[] = static::normalizeUpgradeCostRowOrFail($row, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort(
            $normalized,
            fn (array $left, array $right): int => [
                $left['set_level'],
                $left['from_star'],
                $left['to_star'],
                $left['sort_order'],
                $left['item_id'],
            ] <=> [
                $right['set_level'],
                $right['from_star'],
                $right['to_star'],
                $right['sort_order'],
                $right['item_id'],
            ]
        );

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeUpgradeCostRowOrFail(array $row, string $pathPrefix = 'equipment_star_upgrade_costs'): array
    {
        $errors = [];

        $setLevel = (int) ($row['set_level'] ?? 0);
        $fromStar = (int) ($row['from_star'] ?? -1);
        $toStar = (int) ($row['to_star'] ?? -1);
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $count = (int) ($row['count'] ?? 0);
        $maxStar = EquipmentStarRule::MAX_STAR_BY_SET_LEVEL[$setLevel] ?? null;

        if (! array_key_exists($setLevel, EquipmentStarRule::MAX_STAR_BY_SET_LEVEL)) {
            $errors["{$pathPrefix}.set_level"] = 'set_level 只允许 20 / 40 / 50 / 60。';
        }

        if ($fromStar < 0) {
            $errors["{$pathPrefix}.from_star"] = 'from_star 必须 >= 0。';
        }

        if ($toStar !== $fromStar + 1) {
            $errors["{$pathPrefix}.to_star"] = 'to_star 必须等于 from_star + 1。';
        }

        if ($maxStar !== null && $toStar > $maxStar) {
            $errors["{$pathPrefix}.to_star"] = sprintf('set_level=%d 时 to_star 不能超过 %d。', $setLevel, $maxStar);
        }

        if ($itemId === '') {
            $errors["{$pathPrefix}.item_id"] = 'item_id 不能为空。';
        } elseif (! Item::query()->where('item_id', $itemId)->exists()) {
            $errors["{$pathPrefix}.item_id"] = 'item_id 必须命中已存在的 items.item_id。';
        }

        if ($count <= 0) {
            $errors["{$pathPrefix}.count"] = 'count 必须 > 0。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'set_level' => $setLevel,
            'from_star' => $fromStar,
            'to_star' => $toStar,
            'item_id' => $itemId,
            'count' => $count,
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => static::nullableString($row['remark'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportStarRule(EquipmentStarRule $row): array
    {
        return [
            'set_level' => (int) $row->set_level,
            'max_star' => (int) $row->max_star,
            'summary' => (string) ($row->summary ?? ''),
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => (string) ($row->remark ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportSlotUnlock(EquipmentStarSlotUnlock $row): array
    {
        return [
            'required_star' => (int) $row->required_star,
            'slot_index' => (int) $row->slot_index,
            'slot_group' => (string) $row->slot_group,
            'summary' => (string) ($row->summary ?? ''),
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => (string) ($row->remark ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportProgressionRule(EquipmentStageProgressionRule $row): array
    {
        return [
            'from_set_level' => (int) $row->from_set_level,
            'to_set_level' => (int) $row->to_set_level,
            'required_max_star' => (int) $row->required_max_star,
            'star_keep_mode' => (string) $row->star_keep_mode,
            'summary' => (string) ($row->summary ?? ''),
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => (string) ($row->remark ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportUpgradeCostItem(EquipmentStarUpgradeCost $row): array
    {
        return [
            'item_id' => (string) $row->item_id,
            'count' => (int) $row->count,
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => (string) ($row->remark ?? ''),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
