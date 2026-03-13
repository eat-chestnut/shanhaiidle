<?php

namespace App\Console\Commands;

use App\Services\GiftPackModuleExportService;
use Illuminate\Console\Command;

class ExportGiftPackModuleJson extends Command
{
    protected $signature = 'game:export-gift-pack-module';

    protected $description = 'Export gift pack module to storage/app/exports/gift_pack_module_v1.json';

    public function handle(GiftPackModuleExportService $service): int
    {
        $result = $service->export();

        $this->info(sprintf(
            'Exported %d gift packs -> %s, %s',
            $result['gift_pack_count'],
            $result['latest_path'],
            $result['project_data_path'],
        ));

        return self::SUCCESS;
    }
}
