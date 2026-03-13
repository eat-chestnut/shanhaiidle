<?php

namespace App\Console\Commands;

use App\Services\BattleDefaultsService;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportBattleDefaultsJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/battle_defaults.json';

    protected $signature = 'game:export-battle-defaults';

    protected $description = 'Export battle defaults to storage/app/exports/battle_defaults.json';

    public function handle(): int
    {
        BattleDefaultsService::ensureDefaultSetting();
        $battle = BattleDefaultsService::loadConfig();

        $payloadWithoutMeta = [
            'battle' => $battle,
        ];
        $meta = ExportMetaService::makeMeta('battle_defaults', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('battle_defaults.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'battle_defaults.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported battle defaults -> %s, %s', $path, $projectDataPath));

        return self::SUCCESS;
    }
}
