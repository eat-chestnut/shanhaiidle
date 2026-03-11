<?php

namespace App\Console\Commands;

use App\Models\StoryMap;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportStoryMapsJson extends Command
{
    protected $signature = 'game:export-story-maps';

    protected $description = 'Export enabled story maps to storage/app/exports/story_maps_v1.json';

    public function handle(): int
    {
        $rows = StoryMap::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StoryMap $row): array => [
                'map_id' => (string) $row->map_id,
                'map_name' => (string) $row->map_name,
                'map_order' => (int) $row->map_order,
                'map_type' => (string) $row->map_type,
                'volume_name' => (string) ($row->volume_name ?? ''),
                'source_text' => (string) ($row->source_text ?? ''),
                'level_min' => (int) $row->level_min,
                'level_max' => (int) $row->level_max,
                'theme_tags' => is_array($row->theme_tags) ? array_values($row->theme_tags) : [],
                'atmosphere_desc' => (string) ($row->atmosphere_desc ?? ''),
                'recommend_power' => (int) $row->recommend_power,
                'unlock_condition' => (string) ($row->unlock_condition ?? ''),
                'chapter_id' => (string) ($row->chapter_id ?? ''),
                'boss_id' => (string) ($row->boss_id ?? ''),
                'normal_drop_pool' => (string) ($row->normal_drop_pool ?? ''),
                'elite_drop_pool' => (string) ($row->elite_drop_pool ?? ''),
                'boss_drop_pool' => (string) ($row->boss_drop_pool ?? ''),
                'icon_path' => (string) ($row->icon_path ?? ''),
                'banner_path' => (string) ($row->banner_path ?? ''),
                'bg_path' => (string) ($row->bg_path ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        return $this->writePayload('story_maps', 'story_maps_v1.json', ['story_maps' => $rows]);
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
        $this->info(sprintf('Exported %d rows -> %s (version=%d)', count($payloadWithoutMeta['story_maps']), $path, $version));

        return self::SUCCESS;
    }
}
