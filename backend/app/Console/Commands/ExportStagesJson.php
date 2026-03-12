<?php

namespace App\Console\Commands;

use App\Services\StageExportService;
use Illuminate\Console\Command;

class ExportStagesJson extends Command
{
    protected $signature = 'game:export-stages';

    protected $description = 'Export enabled stages to storage/app/exports/stages_v1.json';

    public function handle(StageExportService $service): int
    {
        $result = $service->export();

        $this->info(sprintf(
            'Exported %d stages -> %s (sha256=%s)',
            (int) $result['count'],
            (string) $result['latest_path'],
            (string) $result['sha256'],
        ));

        return self::SUCCESS;
    }
}
