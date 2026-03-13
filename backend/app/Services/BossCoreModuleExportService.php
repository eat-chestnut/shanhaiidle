<?php

namespace App\Services;

use App\Models\BossCore;
use App\Models\BossCoreEffect;
use App\Support\BossCoreModuleSupport;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BossCoreModuleExportService
{
    private const PROJECT_DATA_FILE = '../data/boss_core_module_v1.json';

    /**
     * @return array{path:string,project_path:string,payload:array<string,mixed>}
     */
    public function exportToProjectFile(): array
    {
        $payloadWithoutMeta = [
            'boss_core_rules' => BossCoreModuleSupport::rulesPayload(),
            'boss_cores' => BossCore::query()
                ->orderBy('sort_order')
                ->orderBy('core_id')
                ->get()
                ->map(fn (BossCore $core): array => BossCoreModuleSupport::exportCore($core))
                ->all(),
            'boss_core_effects' => BossCoreEffect::query()
                ->orderBy('core_id')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (BossCoreEffect $effect): array => BossCoreModuleSupport::exportEffect($effect))
                ->all(),
        ];

        $meta = ExportMetaService::makeMeta('boss_core_module', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('boss_core_module_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'boss_core_module_v1.json';
        $projectPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectPath, $json);

        return [
            'path' => $path,
            'project_path' => $projectPath,
            'payload' => $payload,
        ];
    }
}
