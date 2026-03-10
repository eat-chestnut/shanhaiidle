<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportGemCatalogJson extends Command
{
    protected $signature = 'game:export-gem-catalog';

    protected $description = 'Export enabled gems to storage/app/exports/gem_catalog_v1.json';

    public function handle(): int
    {
        $rows = Item::query()
            ->where('is_enabled', true)
            ->where('type', 'gem')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Item $item): array {
                return [
                    'id' => (string) $item->id,
                    'name' => (string) $item->name,
                    'gem_type' => (string) ($item->sub_type ?? 'attr'),
                    'rarity' => (string) $item->rarity,
                    'effect_type' => (string) ($item->effect_type ?? 'stat'),
                    'target_scope' => (string) ($item->target_scope ?? 'global'),
                    'effect_payload' => is_array($item->effect_payload) ? $item->effect_payload : (is_array($item->gem_effect) ? $item->gem_effect : []),
                    'drop_unlock_level' => (int) ($item->drop_unlock_level ?? 1),
                    'socket_limit' => is_array($item->socket_limit) ? array_values($item->socket_limit) : [],
                    'can_compose' => (bool) ($item->can_compose ?? false),
                    'can_reforge' => (bool) ($item->can_reforge ?? false),
                    'icon' => (string) ($item->icon ?? ''),
                    'sort_order' => (int) ($item->sort_order ?? 0),
                ];
            })
            ->values()
            ->all();

        $payloadWithoutMeta = ['gem_catalog' => $rows];
        $version = ExportMetaService::getNextVersion('gem_catalog');
        $meta = ExportMetaService::makeMeta('gem_catalog', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('gem_catalog_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'gem_catalog_v1.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d gems -> %s (version=%d)', count($rows), $path, $version));

        return self::SUCCESS;
    }
}
