<?php

namespace App\Console\Commands;

use App\Models\PurpleAffix;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportPurpleAffixPoolJson extends Command
{
    protected $signature = 'game:export-purple-affix-pool';

    protected $description = 'Export enabled purple affix pool to storage/app/exports/purple_affix_pool_v1.json';

    public function handle(): int
    {
        $rows = PurpleAffix::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('affix_id')
            ->get()
            ->map(fn (PurpleAffix $row): array => [
                'affix_id' => (string) $row->affix_id,
                'affix_name' => (string) $row->affix_name,
                'stat' => (string) $row->stat,
                'slot_tags' => is_array($row->slot_tags) ? array_values($row->slot_tags) : [],
                'flow_tags' => is_array($row->flow_tags) ? array_values($row->flow_tags) : [],
                'rarity_tier' => (string) $row->rarity_tier,
                'min_value' => (int) $row->min_value,
                'max_value' => (int) $row->max_value,
                'value_mode' => (string) $row->value_mode,
                'weight' => (int) $row->weight,
                'unlock_level' => (int) $row->unlock_level,
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = ['purple_affix_pool' => $rows];
        $version = ExportMetaService::getNextVersion('purple_affix_pool');
        $meta = ExportMetaService::makeMeta('purple_affix_pool', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('purple_affix_pool_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'purple_affix_pool_v1.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d purple affixes -> %s (version=%d)', count($rows), $path, $version));

        return self::SUCCESS;
    }
}
