<?php

namespace App\Services;

use App\Models\Gem;
use App\Support\GemModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GemModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/gem_catalog_v1.json';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到宝石模块数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('gem_catalog_v1.json 解析失败。');
        }

        $rows = is_array($decoded['gem_catalog'] ?? null) ? array_values($decoded['gem_catalog']) : [];

        return GemModuleSupport::normalizeRowsOrFail($rows);
    }

    /**
     * @return array{gem_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $rows = self::loadProjectPayload();

        return DB::transaction(function () use ($rows): array {
            $gemIds = [];

            foreach ($rows as $row) {
                $gemIds[] = (string) $row['gem_id'];

                Gem::query()->updateOrCreate(
                    ['gem_id' => (string) $row['gem_id']],
                    collect($row)->except('gem_id')->all(),
                );
            }

            if ($gemIds === []) {
                Gem::query()->delete();
            } else {
                Gem::query()->whereNotIn('gem_id', $gemIds)->delete();
            }

            return [
                'gem_count' => count($gemIds),
            ];
        });
    }
}
