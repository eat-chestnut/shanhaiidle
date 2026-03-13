<?php

namespace App\Services;

use App\Models\BossCore;
use App\Models\BossCoreEffect;
use App\Support\BossCoreModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BossCoreModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/boss_core_module_v1.json';

    /**
     * @return array{boss_cores: array<int, array<string, mixed>>, boss_core_effects: array<int, array<string, mixed>>}
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到 Boss 核心配置文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('boss_core_module_v1.json 解析失败。');
        }

        return BossCoreModuleSupport::normalizeRowsOrFail(
            is_array($decoded['boss_cores'] ?? null) ? $decoded['boss_cores'] : [],
            is_array($decoded['boss_core_effects'] ?? null) ? $decoded['boss_core_effects'] : [],
        );
    }

    /**
     * @return array{core_count:int,effect_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $coreIds = [];

            foreach ($payload['boss_cores'] as $row) {
                $coreIds[] = (string) $row['core_id'];

                BossCore::query()->updateOrCreate(
                    ['core_id' => (string) $row['core_id']],
                    $row,
                );
            }

            if ($coreIds === []) {
                BossCore::query()->delete();
            } else {
                BossCore::query()->whereNotIn('core_id', $coreIds)->delete();
            }

            BossCoreEffect::query()->delete();
            foreach ($payload['boss_core_effects'] as $row) {
                BossCoreEffect::query()->create($row);
            }

            return [
                'core_count' => count($payload['boss_cores']),
                'effect_count' => count($payload['boss_core_effects']),
            ];
        });
    }
}
