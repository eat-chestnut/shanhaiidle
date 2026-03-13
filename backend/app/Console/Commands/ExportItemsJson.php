<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\ExportMetaService;
use App\Support\AdminOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportItemsJson extends Command
{
    protected $signature = 'game:export-items';

    protected $description = 'Export enabled items to storage/app/exports/items.json';

    public function handle(): int
    {
        $items = Item::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->map(function (Item $item): array {
                $row = [
                    'item_id' => (string) $item->item_id,
                    'item_name' => (string) $item->item_name,
                    'display_name' => (string) $item->display_name,
                    'main_type' => (string) $item->main_type,
                    'main_type_name' => AdminOptions::optionLabel(AdminOptions::itemMainTypeOptions(), (string) $item->main_type),
                    'sub_type' => (string) ($item->sub_type ?? ''),
                    'sub_type_name' => AdminOptions::itemSubTypeLabelByMainType((string) $item->main_type, (string) ($item->sub_type ?? '')),
                    'quality' => (string) $item->quality,
                    'quality_name' => AdminOptions::optionLabel(AdminOptions::qualityOptions(), (string) $item->quality),
                    'rarity' => (string) $item->rarity,
                    'rarity_name' => AdminOptions::optionLabel(AdminOptions::rarityOptions(), (string) $item->rarity),
                    'is_stackable' => (bool) $item->is_stackable,
                    'max_stack' => (int) $item->max_stack,
                    'is_enabled' => (bool) $item->is_enabled,
                    'sort_order' => (int) $item->sort_order,
                    'source_library' => (string) ($item->source_library ?? ''),
                    'source_library_name' => AdminOptions::optionLabel(AdminOptions::itemSourceLibraryOptions(), (string) ($item->source_library ?? '')),
                    'required_level' => (int) $item->required_level,
                    'bind_type' => (string) $item->bind_type,
                    'bind_type_name' => AdminOptions::optionLabel(AdminOptions::itemBindTypeOptions(), (string) $item->bind_type),
                    'sell_price' => (int) $item->sell_price,
                    'use_type' => (string) $item->use_type,
                    'use_type_name' => AdminOptions::optionLabel(AdminOptions::itemUseTypeOptions(), (string) $item->use_type),
                    'id' => (string) $item->item_id,
                    'name' => (string) $item->item_name,
                    'type' => (string) $item->type,
                ];

                if (filled($item->icon)) {
                    $row['icon'] = (string) $item->icon;
                }

                if (filled($item->desc)) {
                    $row['desc'] = (string) $item->desc;
                }

                if (filled($item->remark)) {
                    $row['remark'] = (string) $item->remark;
                }

                if (filled($item->rarity_frame_key)) {
                    $row['rarity_frame_key'] = (string) $item->rarity_frame_key;
                }

                if (filled($item->material_type)) {
                    $row['material_type'] = (string) $item->material_type;
                }

                if (filled($item->effect_type)) {
                    $row['effect_type'] = (string) $item->effect_type;
                }

                if (filled($item->target_scope)) {
                    $row['target_scope'] = (string) $item->target_scope;
                }

                if (is_array($item->effect_payload) && $item->effect_payload !== []) {
                    $row['effect_payload'] = $item->effect_payload;
                }

                if (is_array($item->socket_limit) && $item->socket_limit !== []) {
                    $row['socket_limit'] = array_values($item->socket_limit);
                }

                if (is_array($item->source_tags) && $item->source_tags !== []) {
                    $row['source_tags'] = array_values($item->source_tags);
                    $row['source_tag_names'] = AdminOptions::optionLabels(AdminOptions::itemSourceTagOptions(), $row['source_tags']);
                }

                if (is_array($item->use_tags) && $item->use_tags !== []) {
                    $row['use_tags'] = array_values($item->use_tags);
                    $row['use_tag_names'] = AdminOptions::optionLabels(AdminOptions::itemUseTagOptions(), $row['use_tags']);
                }

                if (filled($item->trait)) {
                    $row['trait'] = (string) $item->trait;
                }

                $row['stack_limit'] = (int) ($item->stack_limit ?? $item->max_stack);
                $row['drop_unlock_level'] = (int) ($item->drop_unlock_level ?? $item->required_level);
                $row['can_compose'] = (bool) ($item->can_compose ?? false);
                $row['can_reforge'] = (bool) ($item->can_reforge ?? false);

                return $row;
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'items' => $items,
            'rarity_colors' => [
                'white' => ['r' => 1.0, 'g' => 1.0, 'b' => 1.0, 'a' => 1.0],
                'blue' => ['r' => 0.35, 'g' => 0.65, 'b' => 1.0, 'a' => 1.0],
                'purple' => ['r' => 0.72, 'g' => 0.45, 'b' => 0.95, 'a' => 1.0],
                'gold' => ['r' => 1.0, 'g' => 0.82, 'b' => 0.35, 'a' => 1.0],
                'red' => ['r' => 0.92, 'g' => 0.28, 'b' => 0.24, 'a' => 1.0],
            ],
        ];
        $meta = ExportMetaService::makeMeta('items', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('items.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'items.json';
        File::put($path, $json);
        File::put(base_path('../data/items.json'), $json);

        $this->info(sprintf('Exported %d items -> %s', count($items), $path));

        return self::SUCCESS;
    }
}
