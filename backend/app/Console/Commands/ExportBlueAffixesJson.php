<?php

namespace App\Console\Commands;

use App\Models\BlueAffix;
use App\Support\BlueAffixModuleSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportBlueAffixesJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/blue_affixes_v1.json';

    protected $signature = 'game:export-blue-affixes';

    protected $description = 'Export enabled blue affixes to storage/app/exports/blue_affixes_v1.json';

    public function handle(): int
    {
        $affixes = BlueAffix::query()
            ->with(['slotRules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('affix_id')
            ->get()
            ->map(fn (BlueAffix $affix): array => BlueAffixModuleSupport::exportAffix($affix))
            ->values()
            ->all();

        $payload = [
            'version' => BlueAffixModuleSupport::VERSION,
            'module' => BlueAffixModuleSupport::MODULE,
            'rules' => BlueAffixModuleSupport::rulesPayload(),
            'affixes' => $affixes,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('blue_affixes_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'blue_affixes_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d blue affixes -> %s, %s', count($affixes), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
