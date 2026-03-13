<?php

namespace App\Services;

use App\Models\Talisman;
use App\Models\TalismanStarLink;
use App\Models\TalismanTier;
use App\Models\TalismanTierUpgradeCost;
use App\Support\TalismanModuleSupport;
use Illuminate\Support\Facades\File;
use RuntimeException;

class TalismanModuleExportService
{
    private const FILE_NAME = 'talisman_module_v1.json';

    private const PROJECT_DATA_FILE = '../data/talisman_module_v1.json';

    /**
     * @return array{talisman_count:int,latest_path:string,project_data_path:string}
     */
    public function export(): array
    {
        $payloadWithoutMeta = [
            'talisman_module' => [
                'rules' => TalismanModuleSupport::rulesPayload(),
                'talismans' => Talisman::query()
                    ->where('is_enabled', true)
                    ->orderBy('sort_order')
                    ->orderBy('talisman_id')
                    ->get()
                    ->map(fn (Talisman $talisman): array => TalismanModuleSupport::exportTalisman($talisman))
                    ->values()
                    ->all(),
                'talisman_tiers' => TalismanTier::query()
                    ->where('is_enabled', true)
                    ->whereHas('talisman', fn ($query) => $query->where('is_enabled', true))
                    ->orderBy('talisman_id')
                    ->orderBy('tier_no')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (TalismanTier $tier): array => TalismanModuleSupport::exportTier($tier))
                    ->values()
                    ->all(),
                'talisman_tier_upgrade_costs' => TalismanTierUpgradeCost::query()
                    ->where('is_enabled', true)
                    ->whereHas('talisman', fn ($query) => $query->where('is_enabled', true))
                    ->orderBy('talisman_id')
                    ->orderBy('tier_no')
                    ->orderBy('target_tier_no')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (TalismanTierUpgradeCost $cost): array => TalismanModuleSupport::exportUpgradeCost($cost))
                    ->values()
                    ->all(),
                'talisman_star_links' => TalismanStarLink::query()
                    ->where('is_enabled', true)
                    ->whereHas('talisman', fn ($query) => $query->where('is_enabled', true))
                    ->orderBy('talisman_id')
                    ->orderBy('tier_no')
                    ->orderBy('required_equipment_star')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (TalismanStarLink $link): array => TalismanModuleSupport::exportStarLink($link))
                    ->values()
                    ->all(),
            ],
        ];
        $payload = ['meta' => ExportMetaService::makeMeta('talisman_module', $payloadWithoutMeta)] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('talisman_module_v1.json 序列化失败。');
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
            'talisman_count' => count($payloadWithoutMeta['talisman_module']['talismans']),
            'latest_path' => $latestPath,
            'project_data_path' => $projectDataPath,
        ];
    }
}
