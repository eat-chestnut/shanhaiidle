<?php

namespace App\Console\Commands;

use App\Models\BlueGearTemplate;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportBlueGearTemplatesJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/blue_gear_templates_v1.json';

    protected $signature = 'game:export-blue-gear-templates';

    protected $description = 'Export enabled blue gear templates to storage/app/exports/blue_gear_templates_v1.json';

    public function handle(): int
    {
        $rows = BlueGearTemplate::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('template_id')
            ->get()
            ->map(fn (BlueGearTemplate $row): array => [
                'id' => (string) $row->template_id,
                'name' => (string) $row->name,
                'blue_pool_id' => (string) ($row->blue_pool_id ?? ''),
                'slot_id' => (string) $row->slot_id,
                'required_level' => (int) $row->required_level,
                'white_stats' => is_array($row->white_stats) ? $row->white_stats : [],
                'min_affix_count' => $row->resolvedMinAffixCount(),
                'max_affix_count' => $row->resolvedMaxAffixCount(),
                'affix_count' => $row->resolvedMaxAffixCount(),
                'blue_affixes' => $row->resolvedAffixEntries(),
                'affix_pool_tags' => is_array($row->affix_pool_tags) ? array_values($row->affix_pool_tags) : [],
                'icon' => (string) ($row->icon ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = ['blue_gear_templates' => $rows];
        $meta = ExportMetaService::makeMeta('blue_gear_templates', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('blue_gear_templates_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'blue_gear_templates_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d blue gear templates -> %s, %s', count($rows), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
