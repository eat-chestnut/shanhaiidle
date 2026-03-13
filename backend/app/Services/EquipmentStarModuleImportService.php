<?php

namespace App\Services;

use App\Models\EquipmentStageProgressionRule;
use App\Models\EquipmentStarRule;
use App\Models\EquipmentStarSlotUnlock;
use App\Models\EquipmentStarUpgradeCost;
use App\Support\EquipmentStarModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class EquipmentStarModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/equipment_star_module_v1.json';

    /**
     * @return array{
     *     rules: array<string, mixed>,
     *     star_rules: array<int, array<string, mixed>>,
     *     slot_unlocks: array<int, array<string, mixed>>,
     *     progression_rules: array<int, array<string, mixed>>,
     *     upgrade_costs: array<int, array<string, mixed>>
     * }
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到套装升星模块数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('equipment_star_module_v1.json 解析失败。');
        }

        return EquipmentStarModuleSupport::normalizeProjectPayloadOrFail($decoded);
    }

    /**
     * @return array{star_rule_count:int,slot_unlock_count:int,progression_rule_count:int,upgrade_cost_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $setLevels = [];
            foreach ($payload['star_rules'] as $row) {
                $setLevels[] = (int) $row['set_level'];
                EquipmentStarRule::query()->updateOrCreate(
                    ['set_level' => (int) $row['set_level']],
                    $row,
                );
            }
            EquipmentStarRule::query()->whereNotIn('set_level', $setLevels)->delete();

            $requiredStars = [];
            foreach ($payload['slot_unlocks'] as $row) {
                $requiredStars[] = (int) $row['required_star'];
                EquipmentStarSlotUnlock::query()->updateOrCreate(
                    ['required_star' => (int) $row['required_star']],
                    $row,
                );
            }
            EquipmentStarSlotUnlock::query()->whereNotIn('required_star', $requiredStars)->delete();

            $fromSetLevels = [];
            foreach ($payload['progression_rules'] as $row) {
                $fromSetLevels[] = (int) $row['from_set_level'];
                EquipmentStageProgressionRule::query()->updateOrCreate(
                    [
                        'from_set_level' => (int) $row['from_set_level'],
                        'to_set_level' => (int) $row['to_set_level'],
                    ],
                    $row,
                );
            }
            EquipmentStageProgressionRule::query()->whereNotIn('from_set_level', $fromSetLevels)->delete();

            EquipmentStarUpgradeCost::query()->delete();
            foreach ($payload['upgrade_costs'] as $row) {
                EquipmentStarUpgradeCost::query()->create($row);
            }

            return [
                'star_rule_count' => count($payload['star_rules']),
                'slot_unlock_count' => count($payload['slot_unlocks']),
                'progression_rule_count' => count($payload['progression_rules']),
                'upgrade_cost_count' => count($payload['upgrade_costs']),
            ];
        });
    }
}
