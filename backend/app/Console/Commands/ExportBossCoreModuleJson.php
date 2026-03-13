<?php

namespace App\Console\Commands;

use App\Services\BossCoreModuleExportService;
use Illuminate\Console\Command;

class ExportBossCoreModuleJson extends Command
{
    protected $signature = 'game:export-boss-core-module';

    protected $description = 'Export boss core module to storage/app/exports/boss_core_module_v1.json';

    public function handle(): int
    {
        $result = app(BossCoreModuleExportService::class)->exportToProjectFile();
        $count = is_array($result['payload']['boss_cores'] ?? null) ? count($result['payload']['boss_cores']) : 0;

        $this->info(sprintf('Exported %d boss cores -> %s, %s', $count, $result['path'], $result['project_path']));

        return self::SUCCESS;
    }
}
