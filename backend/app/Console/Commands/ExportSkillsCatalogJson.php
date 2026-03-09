<?php

namespace App\Console\Commands;

use App\Models\SkillCatalog;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportSkillsCatalogJson extends Command
{
    protected $signature = 'game:export-skills-catalog';

    protected $description = 'Export enabled skills catalog to storage/app/exports/skills_catalog.json';

    public function handle(): int
    {
        $rows = SkillCatalog::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (SkillCatalog $row): array {
                return [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'desc' => (string) ($row->desc ?? ''),
                    'sect' => (string) ($row->sect ?? ''),
                    'sort_order' => (int) $row->sort_order,
                    'is_enabled' => (bool) $row->is_enabled,
                ];
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'skills_catalog' => $rows,
        ];
        $version = ExportMetaService::getNextVersion('skills_catalog');
        $meta = ExportMetaService::makeMeta('skills_catalog', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('skills_catalog.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'skills_catalog.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d skills -> %s (version=%d)', count($rows), $path, $version));

        return self::SUCCESS;
    }
}
