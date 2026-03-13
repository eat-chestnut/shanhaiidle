<?php

namespace App\Services;

use App\Models\Milestone;
use App\Support\MilestoneModuleSupport;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MilestoneModuleExportService
{
    private const FILE_NAME = 'progression_milestones_v1.json';

    private const PROJECT_DATA_FILE = '../data/progression_milestones_v1.json';

    /**
     * @return array{milestone_count:int,latest_path:string,project_data_path:string}
     */
    public function export(): array
    {
        $payloadWithoutMeta = [
            'milestones' => Milestone::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('milestone_id')
                ->get()
                ->map(fn (Milestone $milestone): array => [
                    'milestone_id' => (string) $milestone->milestone_id,
                    'title' => (string) $milestone->title,
                    'display_name' => (string) $milestone->display_name,
                    'condition_type' => (string) $milestone->condition_type,
                    'condition_value' => MilestoneModuleSupport::exportConditionValue($milestone),
                    'pre_milestone_id' => $milestone->pre_milestone_id !== null ? (string) $milestone->pre_milestone_id : null,
                    'reward_item_id' => (string) $milestone->reward_item_id,
                    'reward_count' => (int) $milestone->reward_count,
                    'icon' => $milestone->icon,
                    'summary' => (string) $milestone->summary,
                    'sort_order' => (int) $milestone->sort_order,
                    'is_enabled' => (bool) $milestone->is_enabled,
                    'remark' => $milestone->remark,
                ])
                ->values()
                ->all(),
        ];
        $payload = ['meta' => ExportMetaService::makeMeta('milestones', $payloadWithoutMeta)] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('progression_milestones_v1.json 序列化失败。');
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
            'milestone_count' => count($payloadWithoutMeta['milestones']),
            'latest_path' => $latestPath,
            'project_data_path' => $projectDataPath,
        ];
    }
}
