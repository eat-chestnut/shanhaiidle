<?php

namespace App\Services;

use App\Models\Milestone;
use App\Support\MilestoneModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MilestoneModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/progression_milestones_v1.json';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到里程碑模块数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('progression_milestones_v1.json 解析失败。');
        }

        $rows = is_array($decoded['milestones'] ?? null) ? array_values($decoded['milestones']) : [];
        MilestoneModuleSupport::validateRowsOrFail($rows);

        return array_map(
            fn (array $row): array => MilestoneModuleSupport::normalizeRow($row),
            $rows,
        );
    }

    /**
     * @return array{milestone_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $rows = self::loadProjectPayload();

        return DB::transaction(function () use ($rows): array {
            $milestoneIds = [];

            foreach ($rows as $row) {
                $milestoneIds[] = (string) $row['milestone_id'];

                Milestone::query()->updateOrCreate(
                    ['milestone_id' => (string) $row['milestone_id']],
                    collect($row)->except('milestone_id')->all(),
                );
            }

            if ($milestoneIds === []) {
                Milestone::query()->delete();
            } else {
                Milestone::query()->whereNotIn('milestone_id', $milestoneIds)->delete();
            }

            return [
                'milestone_count' => count($milestoneIds),
            ];
        });
    }
}
