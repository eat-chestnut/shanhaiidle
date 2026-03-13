<?php

namespace App\Console\Commands;

use App\Services\TalismanModuleExportService;
use Illuminate\Console\Command;

class ExportTalismanModuleJson extends Command
{
    protected $signature = 'game:export-talisman-module';

    protected $description = 'Export talisman module to storage/app/exports/talisman_module_v1.json';

    public function handle(TalismanModuleExportService $service): int
    {
        $result = $service->export();

        $this->info(sprintf(
            'Exported %d talismans -> %s, %s',
            $result['talisman_count'],
            $result['latest_path'],
            $result['project_data_path'],
        ));

        return self::SUCCESS;
    }
}
