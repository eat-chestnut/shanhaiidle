<?php

namespace App\Console\Commands;

use App\Services\CharacterGrowthRulesService;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExportCharacterGrowthRulesJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/character_growth_rules_v1.json';

    protected $signature = 'game:export-character-growth-rules';

    protected $description = 'Export character growth rules to storage/app/exports/character_growth_rules_v1.json';

    public function handle(): int
    {
        CharacterGrowthRulesService::ensureDefaultSetting();

        try {
            $rules = CharacterGrowthRulesService::exportConfig();
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
            'character_growth_rules' => $rules,
        ];
        $meta = ExportMetaService::makeMeta('character_growth_rules', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('character_growth_rules_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'character_growth_rules_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported character growth rules -> %s, %s', $path, $projectDataPath));

        return self::SUCCESS;
    }
}
