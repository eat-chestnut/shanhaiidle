<?php

namespace App\Console\Commands;

use App\Models\StoryBoss;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportStoryBossesJson extends Command
{
    protected $signature = 'game:export-story-bosses';

    protected $description = 'Export enabled story bosses to storage/app/exports/story_bosses_v1.json';

    public function handle(): int
    {
        $rows = StoryBoss::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StoryBoss $row): array => [
                'boss_id' => (string) $row->boss_id,
                'boss_name' => (string) $row->boss_name,
                'source_text' => (string) ($row->source_text ?? ''),
                'map_id' => (string) ($row->map_id ?? ''),
                'chapter_id' => (string) ($row->chapter_id ?? ''),
                'boss_type' => (string) $row->boss_type,
                'recommend_level' => (int) $row->recommend_level,
                'recommend_power' => (int) $row->recommend_power,
                'lore_role' => (string) ($row->lore_role ?? ''),
                'visual_tags' => is_array($row->visual_tags) ? array_values($row->visual_tags) : [],
                'combat_tags' => is_array($row->combat_tags) ? array_values($row->combat_tags) : [],
                'intro_copy' => (string) ($row->intro_copy ?? ''),
                'clear_copy' => (string) ($row->clear_copy ?? ''),
                'icon_path' => (string) ($row->icon_path ?? ''),
                'portrait_path' => (string) ($row->portrait_path ?? ''),
                'banner_path' => (string) ($row->banner_path ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        return $this->writePayload('story_bosses', 'story_bosses_v1.json', ['story_bosses' => $rows]);
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
        $this->info(sprintf('Exported %d rows -> %s (version=%d)', count($payloadWithoutMeta['story_bosses']), $path, $version));

        return self::SUCCESS;
    }
}
