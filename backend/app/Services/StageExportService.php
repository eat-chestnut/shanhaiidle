<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Stage;
use App\Models\StageExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class StageExportService
{
    private const EXPORT_DIR = 'app/exports';

    private const LATEST_FILE_NAME = 'stages_v1.json';

    public function export(): array
    {
        $stages = $this->buildStages();
        $payloadWithoutMeta = [
            'stages' => $stages,
        ];
        $version = ExportMetaService::getNextVersion('stages');
        $historyMaxVersion = (int) (StageExport::query()->max('version') ?? 0);
        if ($version <= $historyMaxVersion) {
            $version = $historyMaxVersion + 1;
            AppSetting::setValue('export_version:stages', $version);
        }
        $meta = ExportMetaService::makeMeta('stages', $version, $payloadWithoutMeta);
        $sha256 = (string) ($meta['sha256'] ?? '');
        $exportedAt = (string) ($meta['exported_at'] ?? now()->format('Y-m-d H:i:s'));

        $result = DB::transaction(function () use ($stages, $payloadWithoutMeta, $version, $meta, $sha256, $exportedAt): array {
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
            $versionFileName = "stages_v1_{$version}.json";
            $versionAbsolutePath = $dir . DIRECTORY_SEPARATOR . $versionFileName;

            File::put($latestAbsolutePath, $json);
            File::put($versionAbsolutePath, $json);

            StageExport::query()->create([
                'version' => $version,
                'file_path' => 'exports/' . $versionFileName,
                'sha256' => $sha256,
            ]);

            AppSetting::setValue('stages_version', $version);
            AppSetting::setValue('current_version', $version);

            $this->cleanupHistory(20);

            return [
                'version' => $version,
                'sha256' => $sha256,
                'count' => count($stages),
                'latest_path' => $latestAbsolutePath,
                'version_path' => $versionAbsolutePath,
                'exported_at' => $exportedAt,
            ];
        });

        return $result;
    }

    public function rollbackToVersion(int $version): array
    {
        $record = StageExport::query()->where('version', $version)->first();
        if (! $record) {
            throw new RuntimeException("未找到版本 {$version}。");
        }

        $source = storage_path('app/' . ltrim((string) $record->file_path, '/'));
        if (! is_file($source)) {
            throw new RuntimeException("版本文件不存在：{$record->file_path}");
        }

        $dir = storage_path(self::EXPORT_DIR);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $target = $dir . DIRECTORY_SEPARATOR . self::LATEST_FILE_NAME;
        File::copy($source, $target);

        AppSetting::setValue('current_version', $version);

        return [
            'version' => $version,
            'sha256' => (string) $record->sha256,
            'latest_path' => $target,
        ];
    }

    public function history(): Collection
    {
        return StageExport::query()
            ->orderByDesc('version')
            ->get();
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
                    'elite_every_kills' => (int) $stage->elite_every_kills,
                    'boss_every_kills' => (int) $stage->boss_every_kills,
                    'monsters' => $this->normalizeMonstersPatch($stage->monsters_patch),
                    'spawn_patch' => $stage->spawn_patch ?? null,
                    'drops_patch' => $stage->drops_patch ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeMonstersPatch(?array $patch): array
    {
        $fallback = [
            'normal_pool' => [['id' => 'mob_a', 'w' => 100]],
            'elite_pool' => [['id' => 'elite_a', 'w' => 100]],
            'boss_pool' => [['id' => 'boss_a', 'w' => 100]],
        ];

        if (! is_array($patch)) {
            return $fallback;
        }

        $normalPool = $this->normalizePoolRows($patch['normal_pool'] ?? null, (string) ($patch['normal'] ?? ''));
        $elitePool = $this->normalizePoolRows($patch['elite_pool'] ?? null, (string) ($patch['elite'] ?? ''));
        $bossPool = $this->normalizePoolRows($patch['boss_pool'] ?? null, (string) ($patch['boss'] ?? ''));

        return [
            'normal_pool' => $this->poolOrFallback($normalPool, $fallback['normal_pool']),
            'elite_pool' => $this->poolOrFallback($elitePool, $fallback['elite_pool']),
            'boss_pool' => $this->poolOrFallback($bossPool, $fallback['boss_pool']),
        ];
    }

    private function normalizePoolRows(mixed $poolRaw, string $legacyId = ''): array
    {
        $rows = [];
        if (is_array($poolRaw)) {
            foreach ($poolRaw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $rows[] = [
                    'id' => $id,
                    'w' => max(0, (int) ($row['w'] ?? 0)),
                ];
            }
        }

        $legacyId = trim($legacyId);
        if ($rows === [] && $legacyId !== '') {
            $rows[] = ['id' => $legacyId, 'w' => 100];
        }

        return array_values($rows);
    }

    private function poolOrFallback(array $pool, array $fallback): array
    {
        if ($pool === []) {
            return $fallback;
        }
        $sum = 0;
        foreach ($pool as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sum += max(0, (int) ($row['w'] ?? 0));
        }

        return $sum > 0 ? $pool : $fallback;
    }

    private function cleanupHistory(int $keep): void
    {
        $expired = StageExport::query()
            ->orderByDesc('version')
            ->skip(max(0, $keep))
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($expired as $record) {
            $absolute = storage_path('app/' . ltrim((string) $record->file_path, '/'));
            if (is_file($absolute)) {
                File::delete($absolute);
            }
            $record->delete();
        }
    }
}
