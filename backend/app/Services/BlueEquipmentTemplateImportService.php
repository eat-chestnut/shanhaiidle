<?php

namespace App\Services;

use App\Models\BlueEquipmentTemplate;
use App\Models\BlueEquipmentTemplateBaseStat;
use App\Models\Item;
use App\Support\BlueEquipmentTemplateModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BlueEquipmentTemplateImportService
{
    public const PROJECT_DATA_FILE = '../data/blue_equipment_templates_v1.json';

    /**
     * @return array{
     *     templates: array<int, array<string, mixed>>,
     *     base_stats: array<int, array<string, mixed>>
     * }
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到蓝装模板数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('blue_equipment_templates_v1.json 解析失败。');
        }

        return BlueEquipmentTemplateModuleSupport::normalizeProjectPayloadOrFail($decoded);
    }

    /**
     * @return array{template_count:int,base_stat_count:int,created_item_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $createdItemCount = self::ensureResultItemsExist($payload['templates']);

            $templateIds = [];
            foreach ($payload['templates'] as $row) {
                $templateIds[] = (string) $row['template_id'];

                BlueEquipmentTemplate::query()->updateOrCreate(
                    ['template_id' => (string) $row['template_id']],
                    $row,
                );
            }

            if ($templateIds === []) {
                BlueEquipmentTemplate::query()->delete();
            } else {
                BlueEquipmentTemplate::query()->whereNotIn('template_id', $templateIds)->delete();
            }

            BlueEquipmentTemplateBaseStat::query()->delete();
            foreach ($payload['base_stats'] as $row) {
                BlueEquipmentTemplateBaseStat::query()->create($row);
            }

            return [
                'template_count' => count($payload['templates']),
                'base_stat_count' => count($payload['base_stats']),
                'created_item_count' => $createdItemCount,
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    private static function ensureResultItemsExist(array $templates): int
    {
        $created = 0;

        foreach ($templates as $template) {
            $itemId = (string) ($template['result_item_id'] ?? '');
            if ($itemId === '' || Item::query()->where('item_id', $itemId)->exists()) {
                continue;
            }

            $payload = BlueEquipmentTemplateModuleSupport::minimalResultItemPayload($template);

            Item::query()->create([
                'id' => (string) $payload['item_id'],
                'item_id' => (string) $payload['item_id'],
                'item_name' => (string) $payload['item_name'],
                'display_name' => (string) $payload['display_name'],
                'main_type' => (string) $payload['main_type'],
                'sub_type' => (string) $payload['sub_type'],
                'quality' => (string) $payload['quality'],
                'rarity' => (string) $payload['rarity'],
                'icon' => $payload['icon'] !== '' ? (string) $payload['icon'] : null,
                'desc' => $payload['desc'],
                'is_stackable' => (bool) $payload['is_stackable'],
                'max_stack' => (int) $payload['max_stack'],
                'is_enabled' => (bool) $payload['is_enabled'],
                'sort_order' => (int) $payload['sort_order'],
                'remark' => $payload['remark'] !== '' ? (string) $payload['remark'] : null,
                'source_library' => (string) $payload['source_library'],
                'required_level' => (int) $payload['required_level'],
                'bind_type' => (string) $payload['bind_type'],
                'sell_price' => (int) $payload['sell_price'],
                'use_type' => (string) $payload['use_type'],
                'rarity_frame_key' => null,
                'name' => (string) $payload['item_name'],
                'type' => (string) $payload['type'],
                'material_type' => null,
                'trait' => null,
                'effect_type' => null,
                'target_scope' => null,
                'effect_payload' => null,
                'drop_unlock_level' => (int) $payload['drop_unlock_level'],
                'socket_limit' => $payload['socket_limit'],
                'source_tags' => $payload['source_tags'],
                'use_tags' => $payload['use_tags'],
                'stack_limit' => (int) $payload['stack_limit'],
                'can_compose' => (bool) $payload['can_compose'],
                'can_reforge' => (bool) $payload['can_reforge'],
            ]);
            $created++;
        }

        return $created;
    }
}
