<?php

namespace App\Services;

use App\Models\BlueAffix;
use App\Models\BlueAffixSlotRule;
use App\Support\BlueAffixModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BlueAffixImportService
{
    public const PROJECT_DATA_FILE = '../data/blue_affixes_v1.json';

    /**
     * @return array{affixes: array<int, array<string, mixed>>, slot_rules: array<int, array<string, mixed>>}
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到蓝词条数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('blue_affixes_v1.json 解析失败。');
        }

        return BlueAffixModuleSupport::normalizeProjectPayloadOrFail($decoded);
    }

    /**
     * @return array{affix_count:int,slot_rule_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $affixIds = [];

            foreach ($payload['affixes'] as $row) {
                $affixIds[] = (string) $row['affix_id'];

                BlueAffix::query()->updateOrCreate(
                    ['affix_id' => (string) $row['affix_id']],
                    $row,
                );
            }

            if ($affixIds === []) {
                BlueAffix::query()->delete();
            } else {
                BlueAffix::query()->whereNotIn('affix_id', $affixIds)->delete();
            }

            BlueAffixSlotRule::query()->delete();
            foreach ($payload['slot_rules'] as $row) {
                BlueAffixSlotRule::query()->create($row);
            }

            return [
                'affix_count' => count($payload['affixes']),
                'slot_rule_count' => count($payload['slot_rules']),
            ];
        });
    }
}
