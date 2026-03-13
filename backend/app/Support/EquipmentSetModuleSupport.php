<?php

namespace App\Support;

use App\Models\EquipmentSet;
use App\Models\Item;
use App\Services\ItemCatalogImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EquipmentSetModuleSupport
{
    public static function rulesPayload(): array
    {
        return [
            'supported_levels' => array_map('intval', array_keys(EquipmentSet::SET_LEVEL_OPTIONS)),
            'piece_total_by_level' => EquipmentSet::PIECE_TOTAL_BY_LEVEL,
            'thresholds_by_level' => EquipmentSet::THRESHOLDS_BY_LEVEL,
            'slot_types' => EquipmentSet::SLOT_TYPE_OPTIONS,
            'slot_types_by_level' => EquipmentSet::SLOT_TYPES_BY_LEVEL,
            'craft_rule_summary' => [
                '20级套装仅需要打造附加材料，不需要前一档装备与图纸。',
                '40级打造必须消耗 20级对应部位成品，并要求图纸。',
                '50级打造必须消耗 40级对应部位成品，并要求图纸。',
                '60级打造必须消耗 50级对应部位成品，并要求图纸。',
                '高阶新增部位可通过前一档独立 carrier item 承接同部位主材，但不计入低阶正式套装件数。',
                '套装线不是 item，套装成品通过 items.item_id 承接。',
                '本轮不实现套装升星，后续会在现有结构上继续扩展。',
            ],
            'craft_only' => true,
            'star_upgrade_supported' => false,
            'future_extension_note' => '套装升星后续扩展，不在本轮模块范围内。',
        ];
    }

    public static function equipmentItemOptions(): array
    {
        return AdminOptions::itemOptions(fn (Builder $query): Builder => $query
            ->where('main_type', 'equipment')
            ->where('sub_type', 'set_equipment'));
    }

    public static function blueprintItemOptions(): array
    {
        return AdminOptions::itemOptions(fn (Builder $query): Builder => $query
            ->where('main_type', 'blueprint')
            ->where('sub_type', 'equipment_blueprint'));
    }

    public static function costMaterialOptions(): array
    {
        return AdminOptions::itemOptions(fn (Builder $query): Builder => $query
            ->where('main_type', 'material'));
    }

    /**
     * @return array{
     *     set: array<string, mixed>,
     *     items: array<int, array<string, mixed>>,
     *     effects: array<int, array<string, mixed>>,
     *     recipes: array<int, array<string, mixed>>,
     *     cost_items: array<int, array<string, mixed>>
     * }
     */
    public static function normalizeSingleSetFormOrFail(array $data): array
    {
        $recipeRows = [];
        $costRows = [];
        foreach (is_array($data['recipes'] ?? null) ? $data['recipes'] : [] as $recipeIndex => $recipeRow) {
            if (! is_array($recipeRow)) {
                continue;
            }

            $recipeRows[] = $recipeRow;
            foreach (is_array($recipeRow['cost_items'] ?? null) ? $recipeRow['cost_items'] : [] as $costIndex => $costRow) {
                if (! is_array($costRow)) {
                    continue;
                }

                $costRow['recipe_id'] = $costRow['recipe_id'] ?? ($recipeRow['recipe_id'] ?? null);
                $costRow['_form_index'] = sprintf('%d.%d', $recipeIndex, $costIndex);
                $costRows[] = $costRow;
            }
        }

        $normalized = self::normalizeRowsOrFail(
            [$data],
            is_array($data['set_items'] ?? null) ? $data['set_items'] : [],
            is_array($data['effects'] ?? null) ? $data['effects'] : [],
            $recipeRows,
            $costRows,
        );

        return [
            'set' => $normalized['sets'][0],
            'items' => $normalized['set_items'],
            'effects' => $normalized['effects'],
            'recipes' => $normalized['recipes'],
            'cost_items' => $normalized['cost_items'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sets
     * @param  array<int, array<string, mixed>>  $setItems
     * @param  array<int, array<string, mixed>>  $effects
     * @param  array<int, array<string, mixed>>  $recipes
     * @param  array<int, array<string, mixed>>  $costItems
     * @return array{
     *     sets: array<int, array<string, mixed>>,
     *     set_items: array<int, array<string, mixed>>,
     *     effects: array<int, array<string, mixed>>,
     *     recipes: array<int, array<string, mixed>>,
     *     cost_items: array<int, array<string, mixed>>
     * }
     */
    public static function normalizeRowsOrFail(
        array $sets,
        array $setItems,
        array $effects,
        array $recipes,
        array $costItems,
    ): array {
        $errors = [];

        $normalizedSets = [];
        $seenSetIds = [];
        foreach ($sets as $index => $row) {
            $normalized = self::normalizeSetRow($row, "equipment_sets.{$index}", $errors);
            if ($normalized === null) {
                continue;
            }

            $setId = (string) $normalized['set_id'];
            if (isset($seenSetIds[$setId])) {
                $errors["equipment_sets.{$index}.set_id"] = sprintf('set_id 重复：%s。', $setId);
                continue;
            }

            $seenSetIds[$setId] = true;
            $normalizedSets[] = $normalized;
        }

        $normalizedItems = [];
        foreach ($setItems as $index => $row) {
            $normalized = self::normalizeSetItemRow($row, "equipment_set_items.{$index}", $errors);
            if ($normalized !== null) {
                $normalizedItems[] = $normalized;
            }
        }

        $normalizedEffects = [];
        foreach ($effects as $index => $row) {
            $normalized = self::normalizeEffectRow($row, "equipment_set_effects.{$index}", $errors);
            if ($normalized !== null) {
                $normalizedEffects[] = $normalized;
            }
        }

        $normalizedRecipes = [];
        $seenRecipeIds = [];
        foreach ($recipes as $index => $row) {
            $normalized = self::normalizeRecipeRow($row, "equipment_set_craft_recipes.{$index}", $errors);
            if ($normalized === null) {
                continue;
            }

            $recipeId = (string) $normalized['recipe_id'];
            if (isset($seenRecipeIds[$recipeId])) {
                $errors["equipment_set_craft_recipes.{$index}.recipe_id"] = sprintf('recipe_id 重复：%s。', $recipeId);
                continue;
            }

            $seenRecipeIds[$recipeId] = true;
            $normalizedRecipes[] = $normalized;
        }

        $normalizedCostItems = [];
        foreach ($costItems as $index => $row) {
            $path = $row['_form_index'] ?? $index;
            $normalized = self::normalizeCostItemRow($row, "equipment_set_recipe_cost_items.{$path}", $errors);
            if ($normalized !== null) {
                $normalizedCostItems[] = $normalized;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        self::validateRelationshipsOrFail(
            $normalizedSets,
            $normalizedItems,
            $normalizedEffects,
            $normalizedRecipes,
            $normalizedCostItems,
        );

        return [
            'sets' => array_values($normalizedSets),
            'set_items' => array_values($normalizedItems),
            'effects' => array_values($normalizedEffects),
            'recipes' => array_values($normalizedRecipes),
            'cost_items' => array_values($normalizedCostItems),
        ];
    }

    public static function exportSet(EquipmentSet $set): array
    {
        return [
            'set_id' => (string) $set->set_id,
            'set_name' => (string) $set->set_name,
            'display_name' => (string) $set->display_name,
            'set_level' => (int) $set->set_level,
            'piece_total' => (int) $set->piece_total,
            'set_type' => (string) $set->set_type,
            'quality' => (string) $set->quality,
            'rarity' => (string) $set->rarity,
            'unlock_level' => (int) $set->unlock_level,
            'icon' => $set->icon !== null ? (string) $set->icon : '',
            'summary' => $set->summary !== null ? (string) $set->summary : '',
            'source_desc' => (string) $set->source_desc,
            'sort_order' => (int) $set->sort_order,
            'is_enabled' => (bool) $set->is_enabled,
            'remark' => $set->remark !== null ? (string) $set->remark : '',
        ];
    }

    public static function exportSetItem(\App\Models\EquipmentSetItem $row): array
    {
        return [
            'set_id' => (string) $row->set_id,
            'item_id' => (string) $row->item_id,
            'slot_type' => (string) $row->slot_type,
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => $row->remark !== null ? (string) $row->remark : '',
        ];
    }

    public static function exportEffect(\App\Models\EquipmentSetEffect $row): array
    {
        return [
            'set_id' => (string) $row->set_id,
            'piece_count' => (int) $row->piece_count,
            'effect_key' => (string) $row->effect_key,
            'value_type' => (string) $row->value_type,
            'value' => self::normalizeNumericValue((float) $row->value),
            'summary' => $row->summary !== null ? (string) $row->summary : '',
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => $row->remark !== null ? (string) $row->remark : '',
        ];
    }

    public static function exportRecipe(\App\Models\EquipmentSetCraftRecipe $row): array
    {
        return [
            'recipe_id' => (string) $row->recipe_id,
            'set_id' => (string) $row->set_id,
            'slot_type' => (string) $row->slot_type,
            'result_item_id' => (string) $row->result_item_id,
            'required_base_item_id' => $row->required_base_item_id !== null ? (string) $row->required_base_item_id : '',
            'required_blueprint_item_id' => $row->required_blueprint_item_id !== null ? (string) $row->required_blueprint_item_id : '',
            'unlock_level' => (int) $row->unlock_level,
            'summary' => $row->summary !== null ? (string) $row->summary : '',
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => $row->remark !== null ? (string) $row->remark : '',
        ];
    }

    public static function exportCostItem(\App\Models\EquipmentSetRecipeCostItem $row): array
    {
        return [
            'recipe_id' => (string) $row->recipe_id,
            'item_id' => (string) $row->item_id,
            'count' => (int) $row->count,
            'sort_order' => (int) $row->sort_order,
            'is_enabled' => (bool) $row->is_enabled,
            'remark' => $row->remark !== null ? (string) $row->remark : '',
        ];
    }

    public static function normalizeNumericValue(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : round($value, 4);
    }

    private static function normalizeSetRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = '套装条目格式错误。';
            return null;
        }

        $setId = trim((string) ($row['set_id'] ?? ''));
        $setName = trim((string) ($row['set_name'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        $setLevel = (int) ($row['set_level'] ?? 0);
        $pieceTotal = (int) ($row['piece_total'] ?? 0);
        $setType = trim((string) ($row['set_type'] ?? 'combat_set'));
        $quality = ItemCatalogImportService::normalizeTier((string) ($row['quality'] ?? 'white'));
        $rarity = ItemCatalogImportService::normalizeTier((string) ($row['rarity'] ?? 'white'));

        if ($setId === '') {
            $errors["{$path}.set_id"] = '请填写 set_id。';
        }

        if ($setName === '') {
            $errors["{$path}.set_name"] = '请填写 set_name。';
        }

        if ($displayName === '') {
            $errors["{$path}.display_name"] = '请填写 display_name。';
        }

        if (! array_key_exists($setLevel, EquipmentSet::SET_LEVEL_OPTIONS)) {
            $errors["{$path}.set_level"] = 'set_level 仅支持 20 / 40 / 50 / 60。';
        }

        $expectedPieceTotal = EquipmentSet::PIECE_TOTAL_BY_LEVEL[$setLevel] ?? null;
        if ($expectedPieceTotal === null || $pieceTotal !== $expectedPieceTotal) {
            $errors["{$path}.piece_total"] = 'piece_total 必须与 set_level 对应。';
        }

        if (! array_key_exists($setType, EquipmentSet::SET_TYPE_OPTIONS)) {
            $errors["{$path}.set_type"] = 'set_type 非法。';
        }

        if (! array_key_exists($quality, Item::QUALITY_OPTIONS)) {
            $errors["{$path}.quality"] = 'quality 非法。';
        }

        if (! array_key_exists($rarity, Item::RARITY_OPTIONS)) {
            $errors["{$path}.rarity"] = 'rarity 非法。';
        }

        return [
            'set_id' => $setId,
            'set_name' => $setName,
            'display_name' => $displayName,
            'set_level' => $setLevel,
            'piece_total' => $pieceTotal,
            'set_type' => $setType,
            'quality' => $quality,
            'rarity' => $rarity,
            'unlock_level' => max(1, (int) ($row['unlock_level'] ?? max(1, $setLevel))),
            'icon' => self::nullableString($row['icon'] ?? null),
            'summary' => self::nullableString($row['summary'] ?? null),
            'source_desc' => trim((string) ($row['source_desc'] ?? '')),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => self::nullableString($row['remark'] ?? null),
        ];
    }

    private static function normalizeSetItemRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = '套装成品条目格式错误。';
            return null;
        }

        $setId = trim((string) ($row['set_id'] ?? ''));
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $slotType = trim((string) ($row['slot_type'] ?? ''));

        if ($setId === '') {
            $errors["{$path}.set_id"] = '请填写 set_id。';
        }

        if ($itemId === '') {
            $errors["{$path}.item_id"] = '请填写 item_id。';
        }

        if (! array_key_exists($slotType, EquipmentSet::SLOT_TYPE_OPTIONS)) {
            $errors["{$path}.slot_type"] = 'slot_type 非法。';
        }

        return [
            'set_id' => $setId,
            'item_id' => $itemId,
            'slot_type' => $slotType,
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => self::nullableString($row['remark'] ?? null),
        ];
    }

    private static function normalizeEffectRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = '套装效果条目格式错误。';
            return null;
        }

        $setId = trim((string) ($row['set_id'] ?? ''));
        $effectKey = trim((string) ($row['effect_key'] ?? ''));
        $valueType = trim((string) ($row['value_type'] ?? ''));

        if ($setId === '') {
            $errors["{$path}.set_id"] = '请填写 set_id。';
        }

        if (! array_key_exists((int) ($row['piece_count'] ?? 0), [2 => true, 4 => true, 6 => true, 8 => true])) {
            $errors["{$path}.piece_count"] = 'piece_count 仅支持 2 / 4 / 6 / 8。';
        }

        if (! array_key_exists($effectKey, EquipmentSet::EFFECT_KEY_OPTIONS)) {
            $errors["{$path}.effect_key"] = 'effect_key 非法。';
        }

        if (! array_key_exists($valueType, EquipmentSet::VALUE_TYPE_OPTIONS)) {
            $errors["{$path}.value_type"] = 'value_type 非法。';
        }

        return [
            'set_id' => $setId,
            'piece_count' => (int) ($row['piece_count'] ?? 0),
            'effect_key' => $effectKey,
            'value_type' => $valueType,
            'value' => (float) ($row['value'] ?? 0),
            'summary' => self::nullableString($row['summary'] ?? null),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => self::nullableString($row['remark'] ?? null),
        ];
    }

    private static function normalizeRecipeRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = '套装配方条目格式错误。';
            return null;
        }

        $recipeId = trim((string) ($row['recipe_id'] ?? ''));
        $setId = trim((string) ($row['set_id'] ?? ''));
        $slotType = trim((string) ($row['slot_type'] ?? ''));
        $resultItemId = trim((string) ($row['result_item_id'] ?? ''));

        if ($recipeId === '') {
            $errors["{$path}.recipe_id"] = '请填写 recipe_id。';
        }

        if ($setId === '') {
            $errors["{$path}.set_id"] = '请填写 set_id。';
        }

        if (! array_key_exists($slotType, EquipmentSet::SLOT_TYPE_OPTIONS)) {
            $errors["{$path}.slot_type"] = 'slot_type 非法。';
        }

        if ($resultItemId === '') {
            $errors["{$path}.result_item_id"] = '请填写 result_item_id。';
        }

        return [
            'recipe_id' => $recipeId,
            'set_id' => $setId,
            'slot_type' => $slotType,
            'result_item_id' => $resultItemId,
            'required_base_item_id' => self::nullableString($row['required_base_item_id'] ?? null),
            'required_blueprint_item_id' => self::nullableString($row['required_blueprint_item_id'] ?? null),
            'unlock_level' => max(1, (int) ($row['unlock_level'] ?? 1)),
            'summary' => self::nullableString($row['summary'] ?? null),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => self::nullableString($row['remark'] ?? null),
        ];
    }

    private static function normalizeCostItemRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = '配方附加材料格式错误。';
            return null;
        }

        $recipeId = trim((string) ($row['recipe_id'] ?? ''));
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $count = max(0, (int) ($row['count'] ?? 0));

        if ($recipeId === '') {
            $errors["{$path}.recipe_id"] = '请填写 recipe_id。';
        }

        if ($itemId === '') {
            $errors["{$path}.item_id"] = '请填写 item_id。';
        }

        if ($count < 1) {
            $errors["{$path}.count"] = 'count 必须大于 0。';
        }

        return [
            'recipe_id' => $recipeId,
            'item_id' => $itemId,
            'count' => $count,
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => self::nullableString($row['remark'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sets
     * @param  array<int, array<string, mixed>>  $setItems
     * @param  array<int, array<string, mixed>>  $effects
     * @param  array<int, array<string, mixed>>  $recipes
     * @param  array<int, array<string, mixed>>  $costItems
     */
    private static function validateRelationshipsOrFail(
        array $sets,
        array $setItems,
        array $effects,
        array $recipes,
        array $costItems,
    ): void {
        $errors = [];
        $setMap = collect($sets)->keyBy('set_id');

        $itemIds = collect($setItems)
            ->pluck('item_id')
            ->merge(collect($recipes)->pluck('result_item_id'))
            ->merge(collect($recipes)->pluck('required_base_item_id')->filter())
            ->merge(collect($recipes)->pluck('required_blueprint_item_id')->filter())
            ->merge(collect($costItems)->pluck('item_id'))
            ->filter()
            ->unique()
            ->values();

        $itemsById = Item::query()
            ->whereIn('item_id', $itemIds->all())
            ->get()
            ->keyBy('item_id');

        $setItemsBySet = collect($setItems)->groupBy('set_id');
        $seenSetItemIds = [];
        foreach ($setItems as $index => $row) {
            $set = $setMap->get($row['set_id']);
            if ($set === null) {
                $errors["equipment_set_items.{$index}.set_id"] = 'set_id 未命中 equipment_sets.set_id。';
                continue;
            }

            $item = $itemsById->get($row['item_id']);
            if (! $item instanceof Item) {
                $errors["equipment_set_items.{$index}.item_id"] = 'item_id 必须命中已存在的 items.item_id。';
                continue;
            }

            if ($item->main_type !== 'equipment' || $item->sub_type !== 'set_equipment') {
                $errors["equipment_set_items.{$index}.item_id"] = '套装成品 item 必须是 main_type=equipment 且 sub_type=set_equipment。';
            }

            if (isset($seenSetItemIds[$row['item_id']])) {
                $errors["equipment_set_items.{$index}.item_id"] = '同一套装成品 item 只能归属一条套装线。';
            }
            $seenSetItemIds[$row['item_id']] = true;
        }

        foreach ($sets as $setIndex => $set) {
            $requiredSlots = EquipmentSet::SLOT_TYPES_BY_LEVEL[(int) $set['set_level']] ?? [];
            $setRows = collect($setItemsBySet->get($set['set_id'], []));
            $presentSlots = $setRows->pluck('slot_type')->unique()->values()->all();
            sort($presentSlots);
            $expectedSlots = $requiredSlots;
            sort($expectedSlots);
            if ($presentSlots !== $expectedSlots) {
                $errors["equipment_set_items.{$setIndex}"] = sprintf(
                    '套装 %s 必须完整配置部位：%s。',
                    $set['set_id'],
                    implode(' / ', $requiredSlots)
                );
            }
        }

        $effectsBySet = collect($effects)->groupBy('set_id');
        foreach ($effects as $index => $row) {
            if (! $setMap->has($row['set_id'])) {
                $errors["equipment_set_effects.{$index}.set_id"] = 'set_id 未命中 equipment_sets.set_id。';
            }
        }

        foreach ($sets as $setIndex => $set) {
            $requiredThresholds = EquipmentSet::THRESHOLDS_BY_LEVEL[(int) $set['set_level']] ?? [];
            $thresholds = collect($effectsBySet->get($set['set_id'], []))
                ->pluck('piece_count')
                ->map(fn ($value): int => (int) $value)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($thresholds !== $requiredThresholds) {
                $errors["equipment_set_effects.{$setIndex}"] = sprintf(
                    '套装 %s 必须完整配置件数效果阈值：%s。',
                    $set['set_id'],
                    implode(' / ', $requiredThresholds)
                );
            }
        }

        $recipesBySet = collect($recipes)->groupBy('set_id');
        $recipesById = collect($recipes)->keyBy('recipe_id');
        foreach ($recipes as $index => $row) {
            $set = $setMap->get($row['set_id']);
            if ($set === null) {
                $errors["equipment_set_craft_recipes.{$index}.set_id"] = 'set_id 未命中 equipment_sets.set_id。';
                continue;
            }

            $requiredSlots = EquipmentSet::SLOT_TYPES_BY_LEVEL[(int) $set['set_level']] ?? [];
            if (! in_array($row['slot_type'], $requiredSlots, true)) {
                $errors["equipment_set_craft_recipes.{$index}.slot_type"] = 'slot_type 与当前 set_level 不匹配。';
            }

            $resultItem = $itemsById->get($row['result_item_id']);
            if (! $resultItem instanceof Item) {
                $errors["equipment_set_craft_recipes.{$index}.result_item_id"] = 'result_item_id 必须命中已存在的 items.item_id。';
            } elseif ($resultItem->main_type !== 'equipment' || $resultItem->sub_type !== 'set_equipment') {
                $errors["equipment_set_craft_recipes.{$index}.result_item_id"] = 'result_item_id 必须对应套装成品 item。';
            }
        }

        foreach ($sets as $setIndex => $set) {
            $requiredSlots = EquipmentSet::SLOT_TYPES_BY_LEVEL[(int) $set['set_level']] ?? [];
            $presentSlots = collect($recipesBySet->get($set['set_id'], []))
                ->pluck('slot_type')
                ->unique()
                ->sort()
                ->values()
                ->all();
            $expectedSlots = $requiredSlots;
            sort($expectedSlots);
            if ($presentSlots !== $expectedSlots) {
                $errors["equipment_set_craft_recipes.{$setIndex}"] = sprintf(
                    '套装 %s 必须为每个部位配置打造配方：%s。',
                    $set['set_id'],
                    implode(' / ', $requiredSlots)
                );
            }
        }

        $lineLevelSlotMap = [];
        foreach ($setItems as $row) {
            $set = $setMap->get($row['set_id']);
            if ($set === null) {
                continue;
            }

            $lineKey = EquipmentSet::deriveLineId((string) $row['set_id']);
            $lineLevelSlotMap[sprintf('%s|%d|%s', $lineKey, (int) $set['set_level'], $row['slot_type'])] = $row['item_id'];
        }

        $itemsBySetSlot = collect($setItems)
            ->groupBy(fn (array $row): string => sprintf('%s|%s', $row['set_id'], $row['slot_type']));

        foreach ($recipes as $index => $row) {
            $set = $setMap->get($row['set_id']);
            if ($set === null) {
                continue;
            }

            $mappedItem = $itemsBySetSlot->get(sprintf('%s|%s', $row['set_id'], $row['slot_type']))?->first();
            if (! is_array($mappedItem) || (string) $mappedItem['item_id'] !== (string) $row['result_item_id']) {
                $errors["equipment_set_craft_recipes.{$index}.result_item_id"] = 'result_item_id 必须与 equipment_set_items 中相同 set_id + slot_type 的成品 item 一致。';
            }

            $setLevel = (int) $set['set_level'];
            if ($setLevel === 20) {
                if ($row['required_base_item_id'] !== null) {
                    $errors["equipment_set_craft_recipes.{$index}.required_base_item_id"] = '20级套装不应配置前一档主材料。';
                }
                if ($row['required_blueprint_item_id'] !== null) {
                    $errors["equipment_set_craft_recipes.{$index}.required_blueprint_item_id"] = '20级套装不应配置图纸。';
                }
                continue;
            }

            $previousLevel = match ($setLevel) {
                40 => 20,
                50 => 40,
                60 => 50,
                default => null,
            };

            $lineKey = EquipmentSet::deriveLineId((string) $row['set_id']);
            $expectedBaseItemId = $previousLevel !== null
                ? ($lineLevelSlotMap[sprintf('%s|%d|%s', $lineKey, $previousLevel, $row['slot_type'])] ?? null)
                : null;

            if ($expectedBaseItemId !== null && $row['required_base_item_id'] !== $expectedBaseItemId) {
                $errors["equipment_set_craft_recipes.{$index}.required_base_item_id"] = '40 / 50 / 60 级打造必须消耗前一档同部位套装成品。';
            }
            if ($expectedBaseItemId === null && $row['required_base_item_id'] === null) {
                $errors["equipment_set_craft_recipes.{$index}.required_base_item_id"] = '40 / 50 / 60 级打造必须配置前一档同部位主材料 item。';
            }

            $baseItem = $row['required_base_item_id'] !== null ? $itemsById->get($row['required_base_item_id']) : null;
            if (! $baseItem instanceof Item || $baseItem->main_type !== 'equipment' || $baseItem->sub_type !== 'set_equipment') {
                $errors["equipment_set_craft_recipes.{$index}.required_base_item_id"] = 'required_base_item_id 必须对应前一档套装成品 item。';
            }

            $blueprintId = $row['required_blueprint_item_id'];
            $blueprintItem = $blueprintId !== null ? $itemsById->get($blueprintId) : null;
            if (
                $blueprintId === null
                || ! $blueprintItem instanceof Item
                || $blueprintItem->main_type !== 'blueprint'
                || $blueprintItem->sub_type !== 'equipment_blueprint'
            ) {
                $errors["equipment_set_craft_recipes.{$index}.required_blueprint_item_id"] = '40 / 50 / 60 级打造必须配置图纸 item。';
            }
        }

        $costsByRecipe = collect($costItems)->groupBy('recipe_id');
        foreach ($costItems as $index => $row) {
            $recipe = $recipesById->get($row['recipe_id']);
            if (! is_array($recipe)) {
                $errors["equipment_set_recipe_cost_items.{$index}.recipe_id"] = 'recipe_id 未命中 equipment_set_craft_recipes.recipe_id。';
                continue;
            }

            $item = $itemsById->get($row['item_id']);
            if (! $item instanceof Item) {
                $errors["equipment_set_recipe_cost_items.{$index}.item_id"] = 'item_id 必须命中已存在的 items.item_id。';
                continue;
            }

            if ($item->main_type !== 'material') {
                $errors["equipment_set_recipe_cost_items.{$index}.item_id"] = '附加材料只允许引用 material 类型 item。';
            }

            if ((string) $row['item_id'] === (string) ($recipe['required_base_item_id'] ?? '')
                || (string) $row['item_id'] === (string) ($recipe['required_blueprint_item_id'] ?? '')) {
                $errors["equipment_set_recipe_cost_items.{$index}.item_id"] = '附加材料表不应重复记录主材料或图纸。';
            }
        }

        foreach ($recipes as $index => $row) {
            $set = $setMap->get($row['set_id']);
            if (! is_array($set)) {
                continue;
            }

            if ((int) $set['set_level'] >= 40 && $costsByRecipe->get($row['recipe_id'], collect())->isEmpty()) {
                $errors["equipment_set_recipe_cost_items.{$index}"] = '40 / 50 / 60 级配方必须至少配置 1 条附加材料。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
