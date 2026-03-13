<?php

namespace App\Console\Commands;

use App\Services\MilestoneModuleExportService;
use Illuminate\Console\Command;

class ExportProgressionMilestonesJson extends Command
{
    protected $signature = 'game:export-progression-milestones';

    protected $description = 'Export progression milestones to storage/app/exports/progression_milestones_v1.json';

    public function handle(): int
    {
        $result = app(MilestoneModuleExportService::class)->export();

        $this->info(sprintf(
            'Exported %d milestones -> %s, %s',
            $result['milestone_count'],
            $result['latest_path'],
            $result['project_data_path'],
        ));

        return self::SUCCESS;
    }
}
