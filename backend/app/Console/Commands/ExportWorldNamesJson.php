<?php

namespace App\Console\Commands;

use App\Models\WorldName;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportWorldNamesJson extends Command
{
    protected $signature = 'game:export-world-names';

    protected $description = 'Export enabled world names to storage/app/exports/world_names_v1.json';

    public function handle(): int
    {
        $rows = WorldName::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (WorldName $row): array => [
                'name_id' => (string) $row->name_id,
                'category' => (string) $row->category,
                'sub_category' => (string) ($row->sub_category ?? ''),
                'display_name' => (string) $row->display_name,
                'source_text' => (string) ($row->source_text ?? ''),
                'from_original' => (bool) $row->from_original,
                'naming_note' => (string) ($row->naming_note ?? ''),
                'visual_tags' => is_array($row->visual_tags) ? array_values($row->visual_tags) : [],
                'system_usage' => is_array($row->system_usage) ? array_values($row->system_usage) : [],
                'icon_path' => (string) ($row->icon_path ?? ''),
                'image_path' => (string) ($row->image_path ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        return $this->writePayload('world_names', 'world_names_v1.json', ['world_names' => $rows]);
    }

    private function writePayload(string $key, string $filename, array $payloadWithoutMeta): int
    {
        $version = ExportMetaService::getNextVersion($key);
        $payload = ['meta' => ExportMetaService::makeMeta($key, $version, $payloadWithoutMeta)] + $payloadWithoutMeta;
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("{$filename} 序列化失败。");
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        File::put($path, $json);
        $this->info(sprintf('Exported %d rows -> %s (version=%d)', count($payloadWithoutMeta['world_names']), $path, $version));

        return self::SUCCESS;
    }
}
