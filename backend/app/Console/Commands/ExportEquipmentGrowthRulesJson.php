<?php

namespace App\Console\Commands;

use App\Services\EquipmentGrowthRulesService;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportEquipmentGrowthRulesJson extends Command
{
    protected $signature = 'game:export-equipment-growth-rules';

    protected $description = 'Export equipment growth rules to storage/app/exports/equipment_growth_rules_v1.json';

    public function handle(): int
    {
        EquipmentGrowthRulesService::ensureDefaultSetting();
        $rules = EquipmentGrowthRulesService::loadConfig();

        $payloadWithoutMeta = [
            'equipment_growth_rules' => $rules,
        ];
        $version = ExportMetaService::getNextVersion('equipment_growth_rules');
        $meta = ExportMetaService::makeMeta('equipment_growth_rules', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('equipment_growth_rules_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'equipment_growth_rules_v1.json';
        File::put($path, $json);

        $this->info(sprintf('Exported equipment growth rules -> %s (version=%d)', $path, $version));

        return self::SUCCESS;
    }
}
