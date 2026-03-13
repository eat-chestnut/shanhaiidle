<?php

namespace App\Services;

use App\Models\MainStageChapter;
use App\Models\MainStageDifficulty;
use App\Support\MainStageModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MainStageModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/main_stage_module_v1.json';

    /**
     * @return array{chapters: array<int, array<string, mixed>>}
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到主线模块数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('主线模块 JSON 解析失败。');
        }

        $module = $decoded['main_stage_module'] ?? null;
        if (! is_array($module)) {
            throw new RuntimeException('main_stage_module 节点缺失。');
        }

        $chapters = $module['chapters'] ?? null;
        if (! is_array($chapters)) {
            throw new RuntimeException('main_stage_module.chapters 必须是数组。');
        }

        MainStageModuleSupport::validateChapterRowsOrFail($chapters);

        return [
            'chapters' => array_values($chapters),
        ];
    }

    /**
     * @return array{chapter_count:int,difficulty_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $chapterIds = [];
            $difficultyIds = [];
            $difficultyCount = 0;

            foreach ($payload['chapters'] as $chapterRow) {
                $chapter = MainStageModuleSupport::normalizeChapter($chapterRow);
                $chapterIds[] = $chapter['chapter_id'];

                MainStageChapter::query()->updateOrCreate(
                    ['chapter_id' => $chapter['chapter_id']],
                    collect($chapter)->except('chapter_id')->all(),
                );

                foreach (array_values(is_array($chapterRow['difficulties'] ?? null) ? $chapterRow['difficulties'] : []) as $difficultyRow) {
                    $difficulty = MainStageModuleSupport::normalizeDifficulty($difficultyRow, $chapter['chapter_id']);
                    $difficultyIds[] = $difficulty['difficulty_id'];
                    $difficultyCount++;

                    MainStageDifficulty::query()->updateOrCreate(
                        ['difficulty_id' => $difficulty['difficulty_id']],
                        collect($difficulty)->except('difficulty_id')->all(),
                    );
                }
            }

            MainStageChapter::query()->whereNotIn('chapter_id', $chapterIds)->delete();
            if ($difficultyIds === []) {
                MainStageDifficulty::query()->delete();
            } else {
                MainStageDifficulty::query()->whereNotIn('difficulty_id', $difficultyIds)->delete();
            }

            return [
                'chapter_count' => count($chapterIds),
                'difficulty_count' => $difficultyCount,
            ];
        });
    }
}
