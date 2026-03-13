<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportMaterialCatalogJson extends Command
{
    protected $signature = 'game:export-material-catalog';

    protected $description = 'Export enabled materials/items to storage/app/exports/material_catalog_v1.json';

    public function handle(): int
    {
        $rows = Item::query()
            ->where('is_enabled', true)
            ->whereIn('type', ['material', 'item', 'blueprint', 'blueprint_fragment', 'currency'])
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Item $item): array => [
                'item_id' => (string) $item->item_id,
                'display_name' => (string) $item->display_name,
                'id' => (string) $item->item_id,
                'name' => (string) $item->display_name,
                'type' => (string) $item->type,
                'main_type' => (string) $item->main_type,
                'sub_type' => (string) ($item->sub_type ?? ''),
                'material_type' => (string) ($item->material_type ?? ''),
                'rarity' => (string) $item->rarity,
                'desc' => (string) ($item->desc ?? $item->trait ?? ''),
                'source_tags' => is_array($item->source_tags) ? array_values($item->source_tags) : [],
                'use_tags' => is_array($item->use_tags) ? array_values($item->use_tags) : [],
                'stack_limit' => (int) ($item->stack_limit ?? 9999),
                'icon' => (string) ($item->icon ?? ''),
                'sort_order' => (int) ($item->sort_order ?? 0),
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = ['material_catalog' => $rows];
        $meta = ExportMetaService::makeMeta('material_catalog', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('material_catalog_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'material_catalog_v1.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d material items -> %s', count($rows), $path));

        return self::SUCCESS;
    }
}
