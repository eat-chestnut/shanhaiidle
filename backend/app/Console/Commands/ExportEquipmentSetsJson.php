<?php

namespace App\Console\Commands;

use App\Services\EquipmentSetModuleExportService;
use Illuminate\Console\Command;

class ExportEquipmentSetsJson extends Command
{
    protected $signature = 'game:export-equipment-sets';

    protected $description = 'Export enabled equipment sets to storage/app/exports/equipment_sets.json';

    public function handle(): int
    {
        $result = app(EquipmentSetModuleExportService::class)->exportToProjectFile();
        $count = is_array($result['payload']['equipment_sets'] ?? null) ? count($result['payload']['equipment_sets']) : 0;

        $this->info(sprintf('Exported %d equipment sets -> %s, %s', $count, $result['path'], $result['project_path']));

        return self::SUCCESS;
    }
}
