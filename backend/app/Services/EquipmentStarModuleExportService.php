<?php

namespace App\Services;

use App\Models\EquipmentStageProgressionRule;
use App\Models\EquipmentStarRule;
use App\Models\EquipmentStarSlotUnlock;
use App\Models\EquipmentStarUpgradeCost;
use App\Support\EquipmentStarModuleSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class EquipmentStarModuleExportService
{
    private const FILE_NAME = 'equipment_star_module_v1.json';

    private const PROJECT_DATA_FILE = '../data/equipment_star_module_v1.json';

    /**
     * @return array{star_rule_count:int,slot_unlock_count:int,progression_rule_count:int,upgrade_cost_group_count:int,latest_path:string,project_data_path:string}
     */
    public function export(): array
    {
        $starRules = EquipmentStarRule::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('set_level')
            ->get()
            ->map(fn (EquipmentStarRule $row): array => EquipmentStarModuleSupport::exportStarRule($row))
            ->values()
            ->all();
        EquipmentStarModuleSupport::normalizeStarRuleRowsOrFail($starRules);

        $slotUnlocks = EquipmentStarSlotUnlock::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('required_star')
            ->get()
            ->map(fn (EquipmentStarSlotUnlock $row): array => EquipmentStarModuleSupport::exportSlotUnlock($row))
            ->values()
            ->all();
        EquipmentStarModuleSupport::normalizeSlotUnlockRowsOrFail($slotUnlocks);

        $progressionRules = EquipmentStageProgressionRule::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('from_set_level')
            ->get()
            ->map(fn (EquipmentStageProgressionRule $row): array => EquipmentStarModuleSupport::exportProgressionRule($row))
            ->values()
            ->all();
        EquipmentStarModuleSupport::normalizeProgressionRuleRowsOrFail($progressionRules);

        $upgradeCostGroups = EquipmentStarUpgradeCost::query()
            ->where('is_enabled', true)
            ->orderBy('set_level')
            ->orderBy('from_star')
            ->orderBy('to_star')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (EquipmentStarUpgradeCost $row): string => sprintf('%d:%d:%d', $row->set_level, $row->from_star, $row->to_star))
            ->map(function (Collection $group): array {
                /** @var EquipmentStarUpgradeCost $first */
                $first = $group->first();

                return [
                    'set_level' => (int) $first->set_level,
                    'from_star' => (int) $first->from_star,
                    'to_star' => (int) $first->to_star,
                    'cost_items' => $group
                        ->sortBy([
                            ['sort_order', 'asc'],
                            ['item_id', 'asc'],
                            ['id', 'asc'],
                        ])
                        ->values()
                        ->map(fn (EquipmentStarUpgradeCost $row): array => EquipmentStarModuleSupport::exportUpgradeCostItem($row))
                        ->all(),
                ];
            })
            ->sortBy([
                ['set_level', 'asc'],
                ['from_star', 'asc'],
                ['to_star', 'asc'],
            ])
            ->values()
            ->all();
        EquipmentStarModuleSupport::normalizeUpgradeCostGroupsOrFail($upgradeCostGroups);

        $payload = [
            'version' => EquipmentStarModuleSupport::VERSION,
            'module' => EquipmentStarModuleSupport::MODULE,
            'rules' => EquipmentStarModuleSupport::moduleRulesPayload(),
            'star_rules' => $starRules,
            'slot_unlocks' => $slotUnlocks,
            'progression_rules' => $progressionRules,
            'upgrade_costs' => $upgradeCostGroups,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('equipment_star_module_v1.json 序列化失败。');
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
            'star_rule_count' => count($starRules),
            'slot_unlock_count' => count($slotUnlocks),
            'progression_rule_count' => count($progressionRules),
            'upgrade_cost_group_count' => count($upgradeCostGroups),
            'latest_path' => $latestPath,
            'project_data_path' => $projectDataPath,
        ];
    }
}
