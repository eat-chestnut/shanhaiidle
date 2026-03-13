<?php

namespace App\Console\Commands;

use App\Models\SkillCatalog;
use App\Services\BattleDefaultsService;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportSkillsCatalogJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/skills_catalog.json';

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
                $payload = [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'desc' => (string) ($row->desc ?? ''),
                    'sect' => trim((string) ($row->sect ?? '')) !== ''
                        ? (string) $row->sect
                        : BattleDefaultsService::classNameById((string) ($row->class ?? '')),
                    'class' => (string) ($row->class ?? ''),
                    'type' => (string) ($row->type ?? 'active'),
                    'min_level' => max(1, (int) ($row->min_level ?? 1)),
                    'max_level' => max(1, (int) ($row->max_level ?? 1)),
                    'target_rule' => (string) ($row->target_rule ?? ''),
                    'cd_sec' => (float) ($row->cd_sec ?? 0),
                    'cost_qi' => max(0, (int) ($row->cost_qi ?? 0)),
                    'cast_range' => $row->cast_range !== null ? (float) $row->cast_range : null,
                    'tags' => array_values(array_filter(array_map(
                        static fn ($tag): string => trim((string) $tag),
                        is_array($row->tags) ? $row->tags : []
                    ))),
                    'sort_order' => (int) $row->sort_order,
                    'is_enabled' => (bool) $row->is_enabled,
                ];

                $runtimeBlocks = is_array($row->runtime_blocks) ? $row->runtime_blocks : [];
                foreach ($runtimeBlocks as $key => $value) {
                    if (! is_string($key) || $key === '' || $value === null) {
                        continue;
                    }
                    $payload[$key] = $value;
                }

                return $payload;
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'skills_catalog' => $rows,
        ];
        $meta = ExportMetaService::makeMeta('skills_catalog', $payloadWithoutMeta);
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
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d skills -> %s, %s', count($rows), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
