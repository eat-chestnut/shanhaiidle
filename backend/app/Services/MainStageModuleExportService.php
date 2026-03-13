<?php

namespace App\Services;

use App\Models\MainStageChapter;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MainStageModuleExportService
{
    private const FILE_NAME = 'main_stage_module_v1.json';

    private const PROJECT_DATA_FILE = '../data/main_stage_module_v1.json';

    /**
     * @return array{count:int,latest_path:string,project_data_path:string}
     */
    public function export(): array
    {
        $payloadWithoutMeta = [
            'main_stage_module' => [
                'chapters' => $this->buildChapters(),
            ],
        ];
        $payload = ['meta' => ExportMetaService::makeMeta('main_stage_module', $payloadWithoutMeta)] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('main_stage_module_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $latestPath = $dir . DIRECTORY_SEPARATOR . self::FILE_NAME;
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($latestPath, $json);
        File::put($projectDataPath, $json);

        return [
            'count' => count($payloadWithoutMeta['main_stage_module']['chapters']),
            'latest_path' => $latestPath,
            'project_data_path' => $projectDataPath,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChapters(): array
    {
        return MainStageChapter::query()
            ->with(['difficulties' => fn ($query) => $query->orderBy('sort_order')->orderBy('difficulty_id')])
            ->orderBy('sort_order')
            ->orderBy('chapter_id')
            ->get()
            ->map(function (MainStageChapter $chapter): array {
                return [
                    'chapter_id' => (string) $chapter->chapter_id,
                    'chapter_name' => (string) $chapter->chapter_name,
                    'chapter_type' => (string) $chapter->chapter_type,
                    'chapter_flow_type' => (string) $chapter->chapter_flow_type,
                    'is_functional_chapter' => (bool) $chapter->is_functional_chapter,
                    'has_combat' => (bool) $chapter->has_combat,
                    'has_sect_selection' => (bool) $chapter->has_sect_selection,
                    'has_shanshen_ritual' => (bool) $chapter->has_shanshen_ritual,
                    'suggested_level_min' => (int) $chapter->suggested_level_min,
                    'suggested_level_max' => (int) $chapter->suggested_level_max,
                    'suggested_power' => (int) $chapter->suggested_power,
                    'mountain_name' => (string) $chapter->mountain_name,
                    'boss_display_name' => (string) $chapter->boss_display_name,
                    'unlock_level' => (int) $chapter->unlock_level,
                    'unlock_prev_chapter_id' => $chapter->unlock_prev_chapter_id !== null ? (string) $chapter->unlock_prev_chapter_id : null,
                    'sect_selection_enabled' => (bool) $chapter->sect_selection_enabled,
                    'sect_selection_pool_id' => $chapter->sect_selection_pool_id !== null ? (string) $chapter->sect_selection_pool_id : null,
                    'shanshen_ritual_enabled' => (bool) $chapter->shanshen_ritual_enabled,
                    'next_version_teaser_title' => $chapter->next_version_teaser_title !== null ? (string) $chapter->next_version_teaser_title : null,
                    'next_world_key' => $chapter->next_world_key !== null ? (string) $chapter->next_world_key : null,
                    'teaser_desc' => $chapter->teaser_desc !== null ? (string) $chapter->teaser_desc : null,
                    'remark' => $chapter->remark !== null ? (string) $chapter->remark : null,
                    'sort_order' => (int) $chapter->sort_order,
                    'is_enabled' => (bool) $chapter->is_enabled,
                    'difficulties' => $chapter->difficulties->map(fn ($difficulty): array => [
                        'difficulty_id' => (string) $difficulty->difficulty_id,
                        'difficulty_code' => (string) $difficulty->difficulty_code,
                        'difficulty_name' => (string) $difficulty->difficulty_name,
                        'normal_monster_pool_id' => (string) $difficulty->normal_monster_pool_id,
                        'elite_monster_pool_id' => (string) $difficulty->elite_monster_pool_id,
                        'boss_id' => (string) $difficulty->boss_id,
                        'drop_preview_group_id' => (string) $difficulty->drop_preview_group_id,
                        'first_clear_reward_group_id' => (string) $difficulty->first_clear_reward_group_id,
                        'remark' => $difficulty->remark !== null ? (string) $difficulty->remark : null,
                        'sort_order' => (int) $difficulty->sort_order,
                        'is_enabled' => (bool) $difficulty->is_enabled,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
