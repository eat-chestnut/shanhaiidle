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
                    'difficulties' => $this->normalizeDifficulties($stage->difficulties),
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

    private function normalizeDifficulties(?array $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return [$this->defaultNormalDifficulty()];
        }

        $rows = [];
        foreach ($raw as $idx => $difficultyRaw) {
            if (! is_array($difficultyRaw)) {
                continue;
            }
            $rows[] = $this->normalizeDifficultyRow($difficultyRaw, (int) $idx);
        }

        return $rows === [] ? [$this->defaultNormalDifficulty()] : array_values($rows);
    }

    private function normalizeDifficultyRow(array $row, int $index): array
    {
        $unlock = is_array($row['unlock'] ?? null) ? $row['unlock'] : [];
        $monsterMult = is_array($row['monster_mult'] ?? null) ? $row['monster_mult'] : [];
        $materialCost = $this->normalizeItemCountMap($unlock['material_cost'] ?? []);

        return [
            'name' => $this->normalizeDifficultyName((string) ($row['name'] ?? ''), $index),
            'unlock' => [
                'boss_kills_required' => max(0, (int) ($unlock['boss_kills_required'] ?? 0)),
                'material_cost' => $this->asJsonMap($materialCost),
                'reward' => $this->normalizeReward($unlock['reward'] ?? []),
            ],
            'first_clear_reward' => $this->normalizeReward($row['first_clear_reward'] ?? []),
            'recommend_score' => max(0, (int) ($row['recommend_score'] ?? 0)),
            'monster_mult' => [
                'hp' => $this->normalizeMultiplier($monsterMult['hp'] ?? 1.0),
                'atk' => $this->normalizeMultiplier($monsterMult['atk'] ?? 1.0),
                'def' => $this->normalizeMultiplier($monsterMult['def'] ?? 1.0),
            ],
            'drops_override' => $this->normalizeDropsOverride($row['drops_override'] ?? []),
        ];
    }

    private function normalizeReward(mixed $rewardRaw): array
    {
        $reward = is_array($rewardRaw) ? $rewardRaw : [];
        $items = $this->normalizeItemCountMap($reward['items'] ?? []);

        return [
            'gold' => max(0, (int) ($reward['gold'] ?? 0)),
            'skill_points' => max(0, (int) ($reward['skill_points'] ?? 0)),
            'items' => $this->asJsonMap($items),
        ];
    }

    private function normalizeDropsOverride(mixed $dropsRaw): array|object
    {
        if (! is_array($dropsRaw)) {
            return (object) [];
        }

        $out = [];
        $dropChance = $this->normalizeNullableFloat($dropsRaw['drop_chance'] ?? null);
        if ($dropChance !== null) {
            $out['drop_chance'] = $dropChance;
        }

        $weightsRaw = is_array($dropsRaw['rarity_weights'] ?? null) ? $dropsRaw['rarity_weights'] : [];
        $hasWeights = false;
        $weights = ['white' => 0, 'blue' => 0, 'gold' => 0];
        foreach (['white', 'blue', 'gold'] as $color) {
            if (array_key_exists($color, $weightsRaw) && $weightsRaw[$color] !== null && $weightsRaw[$color] !== '') {
                $weights[$color] = max(0, (int) $weightsRaw[$color]);
                $hasWeights = true;
            }
        }
        if ($hasWeights) {
            $out['rarity_weights'] = $weights;
        }

        $itemsRaw = is_array($dropsRaw['items_by_rarity'] ?? null) ? $dropsRaw['items_by_rarity'] : [];
        $items = [
            'white' => $this->normalizeStringArray($itemsRaw['white'] ?? []),
            'blue' => $this->normalizeStringArray($itemsRaw['blue'] ?? []),
            'gold' => $this->normalizeStringArray($itemsRaw['gold'] ?? []),
        ];
        if ($items['white'] !== [] || $items['blue'] !== [] || $items['gold'] !== []) {
            $out['items_by_rarity'] = $items;
        }

        $specialRaw = is_array($dropsRaw['special'] ?? null) ? $dropsRaw['special'] : [];
        $specialOut = [];
        foreach (['normal', 'elite', 'boss'] as $kind) {
            $kindRaw = is_array($specialRaw[$kind] ?? null) ? $specialRaw[$kind] : [];
            $kindOut = [];

            $extraGemChance = $this->normalizeNullableFloat($kindRaw['extra_gem_chance'] ?? null);
            if ($extraGemChance !== null) {
                $kindOut['extra_gem_chance'] = $extraGemChance;
            }

            $extraGems = $this->normalizeStringArray($kindRaw['extra_gems'] ?? []);
            if ($extraGems !== []) {
                $kindOut['extra_gems'] = $extraGems;
            }

            if ($kind === 'elite' || $kind === 'boss') {
                $punchStoneChance = $this->normalizeNullableFloat($kindRaw['punch_stone_chance'] ?? null);
                if ($punchStoneChance !== null) {
                    $kindOut['punch_stone_chance'] = $punchStoneChance;
                }
            }

            if ($kind === 'boss') {
                $core = trim((string) ($kindRaw['core_guarantee'] ?? ''));
                if ($core !== '') {
                    $kindOut['core_guarantee'] = $core;
                }
            }

            if ($kindOut !== []) {
                $specialOut[$kind] = $kindOut;
            }
        }
        if ($specialOut !== []) {
            $out['special'] = $specialOut;
        }

        if ($out === []) {
            return (object) [];
        }

        return $out;
    }

    private function normalizeItemCountMap(mixed $raw): array
    {
        $map = [];
        if (! is_array($raw)) {
            return $map;
        }

        foreach ($raw as $key => $value) {
            $itemId = '';
            $count = 0;
            if (is_array($value)) {
                $itemId = trim((string) ($value['item_id'] ?? $value['id'] ?? ''));
                $count = (int) ($value['count'] ?? $value['cnt'] ?? 0);
            } elseif (is_string($key)) {
                $itemId = trim($key);
                $count = (int) $value;
            }
            if ($itemId === '' || $count <= 0) {
                continue;
            }

            $map[$itemId] = ($map[$itemId] ?? 0) + $count;
        }

        ksort($map);

        return $map;
    }

    private function normalizeStringArray(mixed $raw): array
    {
        $arr = [];
        if (! is_array($raw)) {
            return $arr;
        }

        foreach ($raw as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $arr[$value] = true;
        }

        return array_values(array_keys($arr));
    }

    private function normalizeNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeMultiplier(mixed $value): float
    {
        $num = is_numeric($value) ? (float) $value : 1.0;
        if (! is_finite($num) || $num <= 0) {
            return 1.0;
        }

        return $num;
    }

    private function normalizeDifficultyName(string $name, int $index): string
    {
        $name = trim($name);
        if ($name !== '') {
            return $name;
        }
        if ($index <= 0) {
            return '普通';
        }

        return '困难' . $index;
    }

    private function defaultNormalDifficulty(): array
    {
        return [
            'name' => '普通',
            'unlock' => [
                'boss_kills_required' => 0,
                'material_cost' => (object) [],
                'reward' => [
                    'gold' => 0,
                    'skill_points' => 0,
                    'items' => (object) [],
                ],
            ],
            'first_clear_reward' => [
                'gold' => 0,
                'skill_points' => 0,
                'items' => (object) [],
            ],
            'recommend_score' => 0,
            'monster_mult' => [
                'hp' => 1.0,
                'atk' => 1.0,
                'def' => 1.0,
            ],
            'drops_override' => (object) [],
        ];
    }

    private function asJsonMap(array $map): array|object
    {
        return $map === [] ? (object) [] : $map;
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
