<?php

namespace App\Console\Commands;

use App\Services\ExportMetaService;
use App\Services\ProgressionMilestonesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExportProgressionMilestonesJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/progression_milestones_v1.json';

    protected $signature = 'game:export-progression-milestones';

    protected $description = 'Export progression milestones to storage/app/exports/progression_milestones_v1.json';

    public function handle(): int
    {
        ProgressionMilestonesService::ensureDefaultSetting();

        try {
            $rules = ProgressionMilestonesService::exportConfig();
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
            'progression_milestones' => $rules,
        ];
        $meta = ExportMetaService::makeMeta('progression_milestones', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('progression_milestones_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'progression_milestones_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported progression milestones -> %s, %s', $path, $projectDataPath));

        return self::SUCCESS;
    }
}
