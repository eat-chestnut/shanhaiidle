<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\ConfigBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportConfigBundle extends Command
{
    protected $signature = 'game:export-bundle';

    protected $description = 'Export config bundle (manifest + 6 json files) to storage/app/exports/bundles/{bundle_id}';

    /**
     * @var array<int, array{key:string, command:string, source:string, filename:string}>
     */
    private const FILES = [
        ['key' => 'stages', 'command' => 'game:export-stages', 'source' => 'stages_v1.json', 'filename' => 'stages_v1.json'],
        ['key' => 'items', 'command' => 'game:export-items', 'source' => 'items.json', 'filename' => 'items.json'],
        ['key' => 'equip_templates', 'command' => 'game:export-equip-templates', 'source' => 'equip_templates.json', 'filename' => 'equip_templates.json'],
        ['key' => 'equipment_sets', 'command' => 'game:export-equipment-sets', 'source' => 'equipment_sets.json', 'filename' => 'equipment_sets.json'],
        ['key' => 'monsters', 'command' => 'game:export-monsters', 'source' => 'monsters.json', 'filename' => 'monsters.json'],
        ['key' => 'skills_catalog', 'command' => 'game:export-skills-catalog', 'source' => 'skills_catalog.json', 'filename' => 'skills_catalog.json'],
        ['key' => 'battle_defaults', 'command' => 'game:export-battle-defaults', 'source' => 'battle_defaults.json', 'filename' => 'battle_defaults.json'],
    ];

    public function handle(): int
    {
        $bundleId = now()->format('Ymd_His');
        $bundleDir = storage_path('app/exports/bundles/' . $bundleId);

        if (! File::exists($bundleDir)) {
            File::makeDirectory($bundleDir, 0755, true);
        }

        $manifestFiles = [];

        try {
            foreach (self::FILES as $spec) {
                $code = Artisan::call($spec['command']);
                if ($code !== self::SUCCESS) {
                    throw new RuntimeException(sprintf('执行命令失败：%s（code=%d）', $spec['command'], $code));
                }

                $srcPath = storage_path('app/exports/' . $spec['source']);
                if (! File::exists($srcPath)) {
                    throw new RuntimeException(sprintf('导出文件不存在：%s', $srcPath));
                }

                $content = File::get($srcPath);
                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    throw new RuntimeException(sprintf('导出文件不是有效 JSON：%s', $spec['source']));
                }

                $version = (int) data_get($decoded, 'meta.version', 0);
                $sha256 = hash('sha256', $content);

                $destPath = $bundleDir . DIRECTORY_SEPARATOR . $spec['filename'];
                File::put($destPath, $content);

                $manifestFiles[] = [
                    'key' => $spec['key'],
                    'filename' => $spec['filename'],
                    'version' => $version,
                    'sha256' => $sha256,
                ];
            }

            $manifest = [
                'meta' => [
                    'bundle_id' => $bundleId,
                    'exported_at' => now()->format('Y-m-d H:i:s'),
                ],
                'files' => $manifestFiles,
            ];

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($manifestJson === false) {
                throw new RuntimeException('manifest.json 序列化失败。');
            }

            $manifestAbsolutePath = $bundleDir . DIRECTORY_SEPARATOR . 'manifest.json';
            File::put($manifestAbsolutePath, $manifestJson);

            $manifestPath = "exports/bundles/{$bundleId}/manifest.json";
            $manifestSha256 = hash('sha256', $manifestJson);

            ConfigBundle::query()->create([
                'bundle_id' => $bundleId,
                'manifest_path' => $manifestPath,
                'sha256' => $manifestSha256,
            ]);

            AppSetting::setValue('latest_bundle_id', $bundleId);
            $this->cleanupHistory(20);

            $this->info(sprintf('配置包导出成功：bundle_id=%s', $bundleId));
            $this->info(sprintf('目录：%s', $bundleDir));
            $this->info(sprintf('manifest: %s', $manifestPath));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (File::exists($bundleDir)) {
                File::deleteDirectory($bundleDir);
            }
            $this->error('导出失败：' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function cleanupHistory(int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $expired = ConfigBundle::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($expired as $record) {
            $bundleId = trim((string) $record->bundle_id);
            if ($bundleId !== '') {
                $dir = storage_path('app/exports/bundles/' . $bundleId);
                if (File::exists($dir)) {
                    File::deleteDirectory($dir);
                }
            }
            $record->delete();
        }
    }
}
