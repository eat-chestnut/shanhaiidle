<?php

namespace App\Services;

use App\Models\Stage;
use App\Support\AdminOptions;
use App\Support\StageConfigSupport;
use Illuminate\Support\Facades\File;
use RuntimeException;

class StageExportService
{
    private const EXPORT_DIR = 'app/exports';

    private const LATEST_FILE_NAME = 'stages_v1.json';

    private const PROJECT_DATA_FILE = '../data/stages_v1.json';

    public function export(): array
    {
        $stages = $this->buildStages();
        $payloadWithoutMeta = [
            'stages' => $stages,
        ];
        $meta = ExportMetaService::makeMeta('stages', $payloadWithoutMeta);
        $sha256 = (string) ($meta['sha256'] ?? '');
        $exportedAt = (string) ($meta['exported_at'] ?? now()->format('Y-m-d H:i:s'));
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('导出序列化失败。');
        }

        $dir = storage_path(self::EXPORT_DIR);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $latestAbsolutePath = $dir . DIRECTORY_SEPARATOR . self::LATEST_FILE_NAME;
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);

        File::put($latestAbsolutePath, $json);
        File::put($projectDataPath, $json);

        return [
            'sha256' => $sha256,
            'count' => count($stages),
            'latest_path' => $latestAbsolutePath,
            'project_data_path' => $projectDataPath,
            'exported_at' => $exportedAt,
        ];
    }

    private function buildStages(): array
    {
        return Stage::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (Stage $stage): array {
                return [
                    'id' => (string) $stage->id,
                    'name' => (string) $stage->name,
                    'unlock_min_level' => (int) $stage->unlock_min_level,
                    'difficulties' => $this->normalizeDifficulties($stage->difficulties),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeDifficulties(mixed $raw): array
    {
        return array_map(function (array $row): array {
            return [
                'difficulty_name' => (string) $row['difficulty_name'],
                'recommended_power' => (int) $row['recommended_power'],
                'spawn_interval' => $row['spawn_interval'],
                'onscreen_limit' => (int) $row['onscreen_limit'],
                'spawn_radius' => (int) $row['spawn_radius'],
                'normal_monsters' => $this->normalizeMonsterPool($row['normal_monsters'] ?? []),
                'elite_monsters' => $this->normalizeMonsterPool($row['elite_monsters'] ?? []),
                'boss_monsters' => $this->normalizeMonsterPool($row['boss_monsters'] ?? []),
                'elite_spawn_rule' => is_array($row['elite_spawn_rule'] ?? null) ? $row['elite_spawn_rule'] : null,
                'boss_spawn_rule' => is_array($row['boss_spawn_rule'] ?? null) ? $row['boss_spawn_rule'] : null,
            ];
        }, StageConfigSupport::normalizeDifficulties($raw));
    }

    private function normalizeMonsterPool(mixed $raw): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $monsterId = trim((string) ($row['monster_id'] ?? ''));
            if ($monsterId === '') {
                continue;
            }

            $rows[] = [
                'monster_id' => $monsterId,
                'monster_name' => AdminOptions::monsterName($monsterId),
                'weight' => max(0, (int) ($row['weight'] ?? 0)),
            ];
        }

        return array_values($rows);
    }
}
