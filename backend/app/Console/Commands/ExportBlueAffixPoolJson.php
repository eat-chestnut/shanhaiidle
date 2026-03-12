<?php

namespace App\Console\Commands;

use App\Models\BlueAffix;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportBlueAffixPoolJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/blue_affix_pool_v1.json';

    protected $signature = 'game:export-blue-affix-pool';

    protected $description = 'Export enabled blue affix pool to storage/app/exports/blue_affix_pool_v1.json';

    public function handle(): int
    {
        $rows = BlueAffix::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('affix_id')
            ->get()
            ->map(fn (BlueAffix $row): array => [
                'affix_id' => (string) $row->affix_id,
                'affix_name' => (string) $row->affix_name,
                'stat' => (string) $row->stat,
                'slot_tags' => is_array($row->slot_tags) ? array_values($row->slot_tags) : [],
                'min_value' => (int) $row->min_value,
                'max_value' => (int) $row->max_value,
                'value_mode' => (string) $row->value_mode,
                'unlock_level' => (int) $row->unlock_level,
                'sort_order' => (int) $row->sort_order,
                'notes' => (string) ($row->notes ?? ''),
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = ['blue_affix_pool' => $rows];
        $meta = ExportMetaService::makeMeta('blue_affix_pool', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('blue_affix_pool_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'blue_affix_pool_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d blue affixes -> %s, %s', count($rows), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
