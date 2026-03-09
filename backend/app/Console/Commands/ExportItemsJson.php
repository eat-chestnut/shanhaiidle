<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\ExportMetaService;
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
            ->orderBy('id')
            ->get()
            ->map(function (Item $item): array {
                $row = [
                    'id' => (string) $item->id,
                    'name' => (string) $item->name,
                    'rarity' => (string) $item->rarity,
                    'type' => (string) $item->type,
                ];

                if (filled($item->icon)) {
                    $row['icon'] = (string) $item->icon;
                }

                if (filled($item->trait)) {
                    $row['trait'] = (string) $item->trait;
                }

                if (is_array($item->gem_effect) && $item->gem_effect !== []) {
                    $row['gem_effect'] = $item->gem_effect;
                }

                return $row;
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'items' => $items,
            'rarity_colors' => [
                'white' => ['r' => 1.0, 'g' => 1.0, 'b' => 1.0, 'a' => 1.0],
                'blue' => ['r' => 0.35, 'g' => 0.65, 'b' => 1.0, 'a' => 1.0],
                'gold' => ['r' => 1.0, 'g' => 0.82, 'b' => 0.35, 'a' => 1.0],
            ],
        ];
        $version = ExportMetaService::getNextVersion('items');
        $meta = ExportMetaService::makeMeta('items', $version, $payloadWithoutMeta);
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

        $this->info(sprintf('Exported %d items -> %s (version=%d)', count($items), $path, $version));

        return self::SUCCESS;
    }
}
