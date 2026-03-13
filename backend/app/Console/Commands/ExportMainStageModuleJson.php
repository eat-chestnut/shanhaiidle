<?php

namespace App\Console\Commands;

use App\Services\MainStageModuleExportService;
use Illuminate\Console\Command;

class ExportMainStageModuleJson extends Command
{
    protected $signature = 'game:export-main-stage-module';

    protected $description = 'Export main stage module to storage/app/exports/main_stage_module_v1.json';

    public function handle(MainStageModuleExportService $service): int
    {
        $result = $service->export();

        $this->info(sprintf(
            'Exported %d main stage chapters -> %s, %s',
            $result['count'],
            $result['latest_path'],
            $result['project_data_path'],
        ));

        return self::SUCCESS;
    }
}
