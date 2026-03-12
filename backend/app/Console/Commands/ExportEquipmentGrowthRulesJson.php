<?php

namespace App\Console\Commands;

use App\Services\EquipmentGrowthRulesService;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExportEquipmentGrowthRulesJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/equipment_growth_rules_v1.json';

    protected $signature = 'game:export-equipment-growth-rules';

    protected $description = 'Export equipment growth rules to storage/app/exports/equipment_growth_rules_v1.json';

    public function handle(): int
    {
        EquipmentGrowthRulesService::ensureDefaultSetting();
        try {
            $rules = EquipmentGrowthRulesService::exportConfig();
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $items) {
                foreach ((array) $items as $message) {
                    $messages[] = (string) $message;
                }
            }

            throw new RuntimeException(implode('；', array_unique($messages)));
        }

        $payloadWithoutMeta = [
            'equipment_growth_rules' => $rules,
        ];
        $meta = ExportMetaService::makeMeta('equipment_growth_rules', $payloadWithoutMeta);
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
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported equipment growth rules -> %s, %s', $path, $projectDataPath));

        return self::SUCCESS;
    }
}
