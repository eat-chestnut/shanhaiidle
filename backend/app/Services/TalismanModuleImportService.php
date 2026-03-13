<?php

namespace App\Services;

use App\Models\Talisman;
use App\Models\TalismanStarLink;
use App\Models\TalismanTier;
use App\Models\TalismanTierUpgradeCost;
use App\Support\TalismanModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class TalismanModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/talisman_module_v1.json';

    /**
     * @return array{talismans: array<int, array<string, mixed>>, talisman_tiers: array<int, array<string, mixed>>, talisman_tier_upgrade_costs: array<int, array<string, mixed>>, talisman_star_links: array<int, array<string, mixed>>}
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到护符模块数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('talisman_module_v1.json 解析失败。');
        }

        $module = $decoded['talisman_module'] ?? null;
        if (! is_array($module)) {
            throw new RuntimeException('talisman_module 节点缺失。');
        }

        $talismans = TalismanModuleSupport::normalizeTalismansOrFail(
            is_array($module['talismans'] ?? null) ? array_values($module['talismans']) : []
        );
        $knownTalismanIds = array_values(array_column($talismans, 'talisman_id'));

        return [
            'talismans' => $talismans,
            'talisman_tiers' => TalismanModuleSupport::normalizeTierRowsOrFail(
                is_array($module['talisman_tiers'] ?? null) ? array_values($module['talisman_tiers']) : [],
                $knownTalismanIds,
            ),
            'talisman_tier_upgrade_costs' => TalismanModuleSupport::normalizeUpgradeCostRowsOrFail(
                is_array($module['talisman_tier_upgrade_costs'] ?? null) ? array_values($module['talisman_tier_upgrade_costs']) : [],
                $knownTalismanIds,
            ),
            'talisman_star_links' => TalismanModuleSupport::normalizeStarLinkRowsOrFail(
                is_array($module['talisman_star_links'] ?? null) ? array_values($module['talisman_star_links']) : [],
                $knownTalismanIds,
            ),
        ];
    }

    /**
     * @return array{talisman_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $talismanIds = [];

            foreach ($payload['talismans'] as $row) {
                $talismanIds[] = (string) $row['talisman_id'];

                Talisman::query()->updateOrCreate(
                    ['talisman_id' => (string) $row['talisman_id']],
                    collect($row)->except('talisman_id')->all(),
                );
            }

            foreach ($talismanIds as $talismanId) {
                TalismanTier::query()->where('talisman_id', $talismanId)->delete();
                TalismanTierUpgradeCost::query()->where('talisman_id', $talismanId)->delete();
                TalismanStarLink::query()->where('talisman_id', $talismanId)->delete();

                foreach ($payload['talisman_tiers'] as $row) {
                    if ((string) $row['talisman_id'] !== $talismanId) {
                        continue;
                    }

                    TalismanTier::query()->create($row);
                }

                foreach ($payload['talisman_tier_upgrade_costs'] as $row) {
                    if ((string) $row['talisman_id'] !== $talismanId) {
                        continue;
                    }

                    TalismanTierUpgradeCost::query()->create($row);
                }

                foreach ($payload['talisman_star_links'] as $row) {
                    if ((string) $row['talisman_id'] !== $talismanId) {
                        continue;
                    }

                    TalismanStarLink::query()->create($row);
                }
            }

            if ($talismanIds === []) {
                Talisman::query()->delete();
            } else {
                Talisman::query()->whereNotIn('talisman_id', $talismanIds)->delete();
            }

            return [
                'talisman_count' => count($talismanIds),
            ];
        });
    }
}
