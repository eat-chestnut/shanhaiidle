<?php

namespace App\Console\Commands;

use App\Models\StoryMapDrop;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportStoryMapDropsJson extends Command
{
    protected $signature = 'game:export-story-map-drops';

    protected $description = 'Export enabled story map drops to storage/app/exports/story_map_drops_v1.json';

    public function handle(): int
    {
        $rows = StoryMapDrop::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StoryMapDrop $row): array => [
                'drop_id' => (string) $row->drop_id,
                'map_id' => (string) $row->map_id,
                'drop_tier' => (string) $row->drop_tier,
                'item_id' => (string) $row->item_id,
                'item_name' => (string) $row->item_name,
                'item_type' => (string) $row->item_type,
                'count_min' => (int) $row->count_min,
                'count_max' => (int) $row->count_max,
                'probability' => (float) $row->probability,
                'first_clear_only' => (bool) $row->first_clear_only,
                'source_desc' => (string) ($row->source_desc ?? ''),
                'icon_path' => (string) ($row->icon_path ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        return $this->writePayload('story_map_drops', 'story_map_drops_v1.json', ['story_map_drops' => $rows]);
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
        $this->info(sprintf('Exported %d rows -> %s (version=%d)', count($payloadWithoutMeta['story_map_drops']), $path, $version));

        return self::SUCCESS;
    }
}
