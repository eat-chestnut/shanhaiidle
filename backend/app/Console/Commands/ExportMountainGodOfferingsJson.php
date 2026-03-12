<?php

namespace App\Console\Commands;

use App\Services\ExportMetaService;
use App\Services\MountainGodOfferingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExportMountainGodOfferingsJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/mountain_god_v1.json';

    protected $signature = 'game:export-mountain-god-offerings';

    protected $description = 'Export mountain god offerings to storage/app/exports/mountain_god_v1.json';

    public function handle(): int
    {
        MountainGodOfferingService::ensureDefaultSetting();

        try {
            $rules = MountainGodOfferingService::exportConfig();
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
            'mountain_god' => $rules,
        ];
        $meta = ExportMetaService::makeMeta('mountain_god', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('mountain_god_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'mountain_god_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported mountain god offerings -> %s, %s', $path, $projectDataPath));

        return self::SUCCESS;
    }
}
