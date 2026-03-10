<?php

namespace App\Console\Commands;

use App\Models\MaterialDungeon;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportMaterialDungeonsJson extends Command
{
    protected $signature = 'game:export-material-dungeons';

    protected $description = 'Export enabled material dungeons to storage/app/exports/material_dungeons_v1.json';

    public function handle(): int
    {
        $rows = MaterialDungeon::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('dungeon_id')
            ->get()
            ->map(fn (MaterialDungeon $row): array => [
                'dungeon_id' => (string) $row->dungeon_id,
                'name' => (string) $row->name,
                'dungeon_type' => (string) $row->dungeon_type,
                'unlock_level' => (int) $row->unlock_level,
                'layer_config' => is_array($row->layer_config) ? $row->layer_config : [],
                'drop_pools' => is_array($row->drop_pools) ? array_values($row->drop_pools) : [],
                'stamina_cost' => (int) $row->stamina_cost,
                'daily_limit' => (int) $row->daily_limit,
                'description' => (string) ($row->description ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = ['material_dungeons' => $rows];
        $version = ExportMetaService::getNextVersion('material_dungeons');
        $meta = ExportMetaService::makeMeta('material_dungeons', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('material_dungeons_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'material_dungeons_v1.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d material dungeons -> %s (version=%d)', count($rows), $path, $version));

        return self::SUCCESS;
    }
}
