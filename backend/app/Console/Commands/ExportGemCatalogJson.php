<?php

namespace App\Console\Commands;

use App\Models\Gem;
use App\Services\ExportMetaService;
use App\Services\GemModuleImportService;
use App\Support\GemModuleSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportGemCatalogJson extends Command
{
    protected $signature = 'game:export-gem-catalog';

    protected $description = 'Export enabled gems to storage/app/exports/gem_catalog_v1.json';

    public function handle(): int
    {
        $rows = Gem::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Gem $gem): array => GemModuleSupport::exportRow($gem))
            ->values()
            ->all();

        $payloadWithoutMeta = ['gem_catalog' => $rows];
        $meta = ExportMetaService::makeMeta('gem_catalog', $payloadWithoutMeta);
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
        File::put(base_path(GemModuleImportService::PROJECT_DATA_FILE), $json);

        $this->info(sprintf('Exported %d gems -> %s', count($rows), $path));

        return self::SUCCESS;
    }
}
