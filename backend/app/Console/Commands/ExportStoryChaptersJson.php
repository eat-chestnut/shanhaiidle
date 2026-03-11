<?php

namespace App\Console\Commands;

use App\Models\StoryChapter;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportStoryChaptersJson extends Command
{
    protected $signature = 'game:export-story-chapters';

    protected $description = 'Export enabled story chapters to storage/app/exports/story_chapters_v1.json';

    public function handle(): int
    {
        $rows = StoryChapter::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StoryChapter $row): array => [
                'chapter_id' => (string) $row->chapter_id,
                'volume_name' => (string) ($row->volume_name ?? ''),
                'chapter_no' => (int) $row->chapter_no,
                'chapter_name' => (string) $row->chapter_name,
                'chapter_role' => (string) $row->chapter_role,
                'map_id' => (string) ($row->map_id ?? ''),
                'level_min' => (int) $row->level_min,
                'level_max' => (int) $row->level_max,
                'intro_copy' => (string) ($row->intro_copy ?? ''),
                'objective_copy' => (string) ($row->objective_copy ?? ''),
                'boss_intro_copy' => (string) ($row->boss_intro_copy ?? ''),
                'boss_id' => (string) ($row->boss_id ?? ''),
                'clear_copy' => (string) ($row->clear_copy ?? ''),
                'next_hook_copy' => (string) ($row->next_hook_copy ?? ''),
                'icon_path' => (string) ($row->icon_path ?? ''),
                'banner_path' => (string) ($row->banner_path ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        return $this->writePayload('story_chapters', 'story_chapters_v1.json', ['story_chapters' => $rows]);
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
        $this->info(sprintf('Exported %d rows -> %s (version=%d)', count($payloadWithoutMeta['story_chapters']), $path, $version));

        return self::SUCCESS;
    }
}
