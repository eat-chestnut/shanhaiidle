<?php

namespace App\Console\Commands;

use App\Services\ExportMetaService;
use App\Services\SectTaskRulesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExportSectTaskRulesJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/sect_tasks_v1.json';

    protected $signature = 'game:export-sect-task-rules';

    protected $description = 'Export sect task rules to storage/app/exports/sect_tasks_v1.json';

    public function handle(): int
    {
        SectTaskRulesService::ensureDefaultSetting();

        try {
            $rules = SectTaskRulesService::exportConfig();
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
            'sect_tasks' => $rules,
        ];
        $meta = ExportMetaService::makeMeta('sect_tasks', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('sect_tasks_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'sect_tasks_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported sect task rules -> %s, %s', $path, $projectDataPath));

        return self::SUCCESS;
    }
}
