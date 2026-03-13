<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ItemCatalogImportService
{
    public const PROJECT_DATA_FILE = '../data/items.json';

    /**
     * @return array{items: array<int, array<string, mixed>>, rarity_colors: array<string, mixed>}
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到物品数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('items.json 解析失败。');
        }

        $rows = $decoded['items'] ?? null;
        if (! is_array($rows)) {
            throw new RuntimeException('items 节点缺失。');
        }

        $normalizedRows = self::normalizeRowsOrFail($rows);

        return [
            'items' => $normalizedRows,
            'rarity_colors' => is_array($decoded['rarity_colors'] ?? null) ? $decoded['rarity_colors'] : [],
        ];
    }

    /**
     * @return array{item_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $itemIds = [];

            foreach ($payload['items'] as $row) {
                $itemIds[] = (string) $row['item_id'];

                Item::query()->updateOrCreate(
                    ['item_id' => (string) $row['item_id']],
                    self::databaseRow($row),
                );
            }

            if ($itemIds === []) {
                Item::query()->delete();
            } else {
                Item::query()->whereNotIn('item_id', $itemIds)->delete();
            }

            return [
                'item_count' => count($itemIds),
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRowsOrFail(array $rows): array
    {
        $errors = [];
        $normalized = [];
        $seenItemIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["items.{$index}"] = '物品条目格式错误。';
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            $itemName = trim((string) ($row['item_name'] ?? ''));
            $displayName = trim((string) ($row['display_name'] ?? ''));
            $mainType = trim((string) ($row['main_type'] ?? ''));
            $subType = trim((string) ($row['sub_type'] ?? ''));
            $quality = static::normalizeTier((string) ($row['quality'] ?? 'white'));
            $rarity = static::normalizeTier((string) ($row['rarity'] ?? 'white'));
            $bindType = trim((string) ($row['bind_type'] ?? 'none'));
            $useType = trim((string) ($row['use_type'] ?? 'none'));

            if ($itemId === '') {
                $errors["items.{$index}.item_id"] = '请填写 item_id。';
            } elseif (isset($seenItemIds[$itemId])) {
                $errors["items.{$index}.item_id"] = sprintf('item_id 重复：%s。', $itemId);
            } else {
                $seenItemIds[$itemId] = true;
            }

            if ($itemName === '') {
                $errors["items.{$index}.item_name"] = '请填写内部名称。';
            }

            if ($displayName === '') {
                $errors["items.{$index}.display_name"] = '请填写展示名称。';
            }

            if (! array_key_exists($mainType, Item::MAIN_TYPE_OPTIONS)) {
                $errors["items.{$index}.main_type"] = '主类型非法。';
            }

            $validSubTypes = Item::SUB_TYPE_OPTIONS[$mainType] ?? [];
            if ($subType !== '' && $validSubTypes !== [] && ! array_key_exists($subType, $validSubTypes)) {
                $errors["items.{$index}.sub_type"] = '子类型非法。';
            }

            if (! array_key_exists($quality, Item::QUALITY_OPTIONS)) {
                $errors["items.{$index}.quality"] = 'quality 非法。';
            }

            if (! array_key_exists($rarity, Item::RARITY_OPTIONS)) {
                $errors["items.{$index}.rarity"] = 'rarity 非法。';
            }

            if (! array_key_exists($bindType, Item::BIND_TYPE_OPTIONS)) {
                $errors["items.{$index}.bind_type"] = 'bind_type 非法。';
            }

            if (! array_key_exists($useType, Item::USE_TYPE_OPTIONS)) {
                $errors["items.{$index}.use_type"] = 'use_type 非法。';
            }

            $isStackable = (bool) ($row['is_stackable'] ?? true);
            $maxStack = max(1, (int) ($row['max_stack'] ?? 9999));
            $requiredLevel = max(1, (int) ($row['required_level'] ?? 1));
            $sellPrice = max(0, (int) ($row['sell_price'] ?? 0));
            $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));
            $sourceLibrary = trim((string) ($row['source_library'] ?? ''));

            if ($sourceLibrary === '') {
                $errors["items.{$index}.source_library"] = '请填写 source_library。';
            }

            $normalized[] = [
                'item_id' => $itemId,
                'item_name' => $itemName,
                'display_name' => $displayName,
                'main_type' => $mainType,
                'sub_type' => $subType !== '' ? $subType : null,
                'quality' => $quality,
                'rarity' => $rarity,
                'icon' => trim((string) ($row['icon'] ?? '')),
                'desc' => filled($row['desc'] ?? null) ? trim((string) $row['desc']) : null,
                'is_stackable' => $isStackable,
                'max_stack' => $maxStack,
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'sort_order' => $sortOrder,
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
                'source_library' => $sourceLibrary,
                'required_level' => $requiredLevel,
                'bind_type' => $bindType,
                'sell_price' => $sellPrice,
                'use_type' => $useType,
                'rarity_frame_key' => filled($row['rarity_frame_key'] ?? null) ? trim((string) $row['rarity_frame_key']) : null,
                'legacy_type' => static::legacyType($mainType, $subType),
                'legacy_material_type' => static::legacyMaterialType($mainType, $subType),
                'legacy_trait' => filled($row['trait'] ?? null) ? trim((string) $row['trait']) : null,
                'effect_type' => filled($row['effect_type'] ?? null) ? trim((string) $row['effect_type']) : null,
                'target_scope' => filled($row['target_scope'] ?? null) ? trim((string) $row['target_scope']) : null,
                'effect_payload' => is_array($row['effect_payload'] ?? null) ? $row['effect_payload'] : null,
                'drop_unlock_level' => max(1, (int) ($row['drop_unlock_level'] ?? $requiredLevel)),
                'socket_limit' => is_array($row['socket_limit'] ?? null) ? array_values($row['socket_limit']) : null,
                'source_tags' => is_array($row['source_tags'] ?? null) ? array_values($row['source_tags']) : [],
                'use_tags' => is_array($row['use_tags'] ?? null) ? array_values($row['use_tags']) : [],
                'stack_limit' => max(1, (int) ($row['stack_limit'] ?? $maxStack)),
                'can_compose' => (bool) ($row['can_compose'] ?? false),
                'can_reforge' => (bool) ($row['can_reforge'] ?? false),
            ];
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
    private static function databaseRow(array $row): array
    {
        return [
            'id' => (string) $row['item_id'],
            'item_name' => (string) $row['item_name'],
            'display_name' => (string) $row['display_name'],
            'main_type' => (string) $row['main_type'],
            'sub_type' => $row['sub_type'],
            'quality' => (string) $row['quality'],
            'rarity' => (string) $row['rarity'],
            'icon' => filled($row['icon'] ?? null) ? (string) $row['icon'] : null,
            'desc' => $row['desc'],
            'is_stackable' => (bool) $row['is_stackable'],
            'max_stack' => (int) $row['max_stack'],
            'is_enabled' => (bool) $row['is_enabled'],
            'sort_order' => (int) $row['sort_order'],
            'remark' => $row['remark'],
            'source_library' => (string) $row['source_library'],
            'required_level' => (int) $row['required_level'],
            'bind_type' => (string) $row['bind_type'],
            'sell_price' => (int) $row['sell_price'],
            'use_type' => (string) $row['use_type'],
            'rarity_frame_key' => $row['rarity_frame_key'],
            'name' => (string) $row['item_name'],
            'type' => (string) $row['legacy_type'],
            'material_type' => $row['legacy_material_type'],
            'trait' => $row['legacy_trait'],
            'effect_type' => $row['effect_type'],
            'target_scope' => $row['target_scope'],
            'effect_payload' => $row['effect_payload'],
            'drop_unlock_level' => (int) $row['drop_unlock_level'],
            'socket_limit' => $row['socket_limit'],
            'source_tags' => $row['source_tags'],
            'use_tags' => $row['use_tags'],
            'stack_limit' => (int) $row['stack_limit'],
            'can_compose' => (bool) $row['can_compose'],
            'can_reforge' => (bool) $row['can_reforge'],
        ];
    }

    public static function normalizeTier(string $value): string
    {
        $value = trim($value) !== '' ? trim($value) : 'white';

        return $value === 'orange' ? 'red' : $value;
    }

    public static function legacyType(string $mainType, ?string $subType): string
    {
        return match ($mainType) {
            'currency' => 'currency',
            'material' => 'material',
            'gem' => 'gem',
            'blueprint' => 'blueprint',
            'blueprint_fragment' => 'blueprint_fragment',
            'gift_pack', 'consumable', 'equipment', 'talisman' => 'item',
            default => 'item',
        };
    }

    public static function legacyMaterialType(string $mainType, ?string $subType): ?string
    {
        return match ($mainType) {
            'currency' => 'currency',
            'material' => match ($subType) {
                'boss_material' => 'boss',
                'upgrade_material', 'function_material' => 'craft',
                'gem_material' => 'gem',
                'talisman_material' => 'refine',
                'refine_material' => 'refine',
                'star_material' => 'star',
                'story_material' => 'story',
                default => 'craft',
            },
            'gem' => 'gem',
            'blueprint' => 'blueprint',
            'blueprint_fragment' => 'blueprint_fragment',
            'gift_pack' => 'pack',
            'consumable' => match ($subType) {
                'ticket' => 'dungeon_ticket',
                default => 'pack',
            },
            default => $subType,
        };
    }
}
