<?php

namespace App\Console\Commands;

use App\Services\EquipmentStarModuleExportService;
use Illuminate\Console\Command;

class ExportEquipmentStarModuleJson extends Command
{
    protected $signature = 'game:export-equipment-star-module';

    protected $description = 'Export equipment star module to storage/app/exports/equipment_star_module_v1.json';

    public function handle(EquipmentStarModuleExportService $service): int
    {
        $result = $service->export();

        $this->info(sprintf(
            'Exported equipment star module: rules=%d, slot_unlocks=%d, progression_rules=%d, upgrade_cost_groups=%d -> %s',
            $result['star_rule_count'],
            $result['slot_unlock_count'],
            $result['progression_rule_count'],
            $result['upgrade_cost_group_count'],
            $result['latest_path'],
        ));

        return self::SUCCESS;
    }
}
