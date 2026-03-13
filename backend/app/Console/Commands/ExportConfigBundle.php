<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\ConfigBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportConfigBundle extends Command
{
    private const MANIFEST_FILE = 'manifest.json';

    private const BUNDLE_FILE = 'config_bundle_v1.json';

    protected $signature = 'game:export-bundle';

    protected $description = 'Export config bundle (single version manifest + aggregated bundle) to storage/app/exports/bundles/{bundle_id}';

    /**
     * @var array<int, array{key:string, command:string, source:string, filename:string}>
     */
    private const FILES = [
        ['key' => 'stages', 'command' => 'game:export-stages', 'source' => 'stages_v1.json', 'filename' => 'stages_v1.json'],
        ['key' => 'items', 'command' => 'game:export-items', 'source' => 'items.json', 'filename' => 'items.json'],
        ['key' => 'gift_pack_module', 'command' => 'game:export-gift-pack-module', 'source' => 'gift_pack_module_v1.json', 'filename' => 'gift_pack_module_v1.json'],
        ['key' => 'equip_templates', 'command' => 'game:export-equip-templates', 'source' => 'equip_templates.json', 'filename' => 'equip_templates.json'],
        ['key' => 'equipment_sets', 'command' => 'game:export-equipment-sets', 'source' => 'equipment_sets.json', 'filename' => 'equipment_sets.json'],
        ['key' => 'equipment_star_module', 'command' => 'game:export-equipment-star-module', 'source' => 'equipment_star_module_v1.json', 'filename' => 'equipment_star_module_v1.json'],
        ['key' => 'equip_slots', 'command' => 'game:export-equip-slots', 'source' => 'equip_slots_v1.json', 'filename' => 'equip_slots_v1.json'],
        ['key' => 'equipment_growth_rules', 'command' => 'game:export-equipment-growth-rules', 'source' => 'equipment_growth_rules_v1.json', 'filename' => 'equipment_growth_rules_v1.json'],
        ['key' => 'character_growth_rules', 'command' => 'game:export-character-growth-rules', 'source' => 'character_growth_rules_v1.json', 'filename' => 'character_growth_rules_v1.json'],
        ['key' => 'milestones', 'command' => 'game:export-progression-milestones', 'source' => 'progression_milestones_v1.json', 'filename' => 'progression_milestones_v1.json'],
        ['key' => 'blue_equipment_templates', 'command' => 'game:export-blue-equipment-templates', 'source' => 'blue_equipment_templates_v1.json', 'filename' => 'blue_equipment_templates_v1.json'],
        ['key' => 'blue_affixes', 'command' => 'game:export-blue-affixes', 'source' => 'blue_affixes_v1.json', 'filename' => 'blue_affixes_v1.json'],
        ['key' => 'purple_affix_pool', 'command' => 'game:export-purple-affix-pool', 'source' => 'purple_affix_pool_v1.json', 'filename' => 'purple_affix_pool_v1.json'],
        ['key' => 'gem_catalog', 'command' => 'game:export-gem-catalog', 'source' => 'gem_catalog_v1.json', 'filename' => 'gem_catalog_v1.json'],
        ['key' => 'talisman_module', 'command' => 'game:export-talisman-module', 'source' => 'talisman_module_v1.json', 'filename' => 'talisman_module_v1.json'],
        ['key' => 'boss_core_module', 'command' => 'game:export-boss-core-module', 'source' => 'boss_core_module_v1.json', 'filename' => 'boss_core_module_v1.json'],
        ['key' => 'material_catalog', 'command' => 'game:export-material-catalog', 'source' => 'material_catalog_v1.json', 'filename' => 'material_catalog_v1.json'],
        ['key' => 'daily_dungeons', 'command' => 'game:export-daily-dungeons', 'source' => 'daily_dungeons_v1.json', 'filename' => 'daily_dungeons_v1.json'],
        ['key' => 'sect_tasks', 'command' => 'game:export-sect-task-rules', 'source' => 'sect_tasks_v1.json', 'filename' => 'sect_tasks_v1.json'],
        ['key' => 'mountain_god', 'command' => 'game:export-mountain-god-offerings', 'source' => 'mountain_god_v1.json', 'filename' => 'mountain_god_v1.json'],
        ['key' => 'shop_goods', 'command' => 'game:export-shop-goods', 'source' => 'shop_goods_v1.json', 'filename' => 'shop_goods_v1.json'],
        ['key' => 'crafting_recipes', 'command' => 'game:export-crafting-recipes', 'source' => 'crafting_recipes_v1.json', 'filename' => 'crafting_recipes_v1.json'],
        ['key' => 'world_names', 'command' => 'game:export-world-names', 'source' => 'world_names_v1.json', 'filename' => 'world_names_v1.json'],
        ['key' => 'main_stage_module', 'command' => 'game:export-main-stage-module', 'source' => 'main_stage_module_v1.json', 'filename' => 'main_stage_module_v1.json'],
        ['key' => 'story_chapters', 'command' => 'game:export-story-chapters', 'source' => 'story_chapters_v1.json', 'filename' => 'story_chapters_v1.json'],
        ['key' => 'story_maps', 'command' => 'game:export-story-maps', 'source' => 'story_maps_v1.json', 'filename' => 'story_maps_v1.json'],
        ['key' => 'story_bosses', 'command' => 'game:export-story-bosses', 'source' => 'story_bosses_v1.json', 'filename' => 'story_bosses_v1.json'],
        ['key' => 'equipment_set_sources', 'command' => 'game:export-equipment-set-sources', 'source' => 'equipment_set_sources_v1.json', 'filename' => 'equipment_set_sources_v1.json'],
        ['key' => 'monsters', 'command' => 'game:export-monsters', 'source' => 'monsters.json', 'filename' => 'monsters.json'],
        ['key' => 'skills_catalog', 'command' => 'game:export-skills-catalog', 'source' => 'skills_catalog.json', 'filename' => 'skills_catalog.json'],
        ['key' => 'battle_defaults', 'command' => 'game:export-battle-defaults', 'source' => 'battle_defaults.json', 'filename' => 'battle_defaults.json'],
    ];

    public function handle(): int
    {
        $bundleId = now()->format('Ymd_His');
        $bundleDir = storage_path('app/exports/bundles/' . $bundleId);
        $latestExportsDir = storage_path('app/exports');

        if (! File::exists($bundleDir)) {
            File::makeDirectory($bundleDir, 0755, true);
        }

        $previousVersions = $this->loadPreviousVersions();
        $manifestFiles = [];
        $bundleConfigs = [];

        try {
            foreach (self::FILES as $spec) {
                $srcPath = $latestExportsDir . DIRECTORY_SEPARATOR . $spec['source'];
                if (! File::exists($srcPath)) {
                    throw new RuntimeException(sprintf('导出文件不存在：%s，请先执行 %s。', $srcPath, $spec['command']));
                }

                $content = File::get($srcPath);
                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    throw new RuntimeException(sprintf('导出文件不是有效 JSON：%s', $spec['source']));
                }

                $sha256 = hash('sha256', $content);
                $size = File::size($srcPath);
                $updatedAt = (string) data_get($decoded, 'meta.exported_at', now()->format('Y-m-d H:i:s'));
                $previous = $previousVersions[$spec['key']] ?? null;
                $version = 1;
                if (is_array($previous)) {
                    $previousVersion = max(0, (int) ($previous['version'] ?? 0));
                    $previousSha256 = (string) ($previous['sha256'] ?? '');
                    $version = $previousSha256 === $sha256 ? max(1, $previousVersion) : ($previousVersion + 1);
                }

                $destPath = $bundleDir . DIRECTORY_SEPARATOR . $spec['filename'];
                File::put($destPath, $content);

                $manifestFiles[] = [
                    'key' => $spec['key'],
                    'filename' => $spec['filename'],
                    'version' => $version,
                    'sha256' => $sha256,
                    'size' => $size,
                    'updated_at' => $updatedAt,
                ];
                $bundleConfigs[$spec['key']] = $decoded;
            }

            $manifest = [
                'meta' => [
                    'kind' => 'config_manifest',
                    'schema_version' => 1,
                    'bundle_id' => $bundleId,
                    'generated_at' => now()->format('Y-m-d H:i:s'),
                ],
                'files' => $manifestFiles,
            ];

            $bundlePayload = [
                'meta' => [
                    'kind' => 'config_bundle',
                    'schema_version' => 1,
                    'bundle_id' => $bundleId,
                    'generated_at' => now()->format('Y-m-d H:i:s'),
                    'manifest_filename' => self::MANIFEST_FILE,
                ],
                'configs' => $bundleConfigs,
            ];

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($manifestJson === false) {
                throw new RuntimeException('manifest.json 序列化失败。');
            }

            $bundleJson = json_encode($bundlePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($bundleJson === false) {
                throw new RuntimeException('config_bundle_v1.json 序列化失败。');
            }

            $manifestAbsolutePath = $bundleDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
            $bundleAbsolutePath = $bundleDir . DIRECTORY_SEPARATOR . self::BUNDLE_FILE;
            File::put($manifestAbsolutePath, $manifestJson);
            File::put($bundleAbsolutePath, $bundleJson);
            File::put($latestExportsDir . DIRECTORY_SEPARATOR . self::BUNDLE_FILE, $bundleJson);

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
            $this->info(sprintf('bundle: %s', "exports/bundles/{$bundleId}/" . self::BUNDLE_FILE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (File::exists($bundleDir)) {
                File::deleteDirectory($bundleDir);
            }
            $this->error('导出失败：' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, array{version:int, sha256:string}>
     */
    private function loadPreviousVersions(): array
    {
        $bundleId = trim((string) AppSetting::getValue('latest_bundle_id', ''));
        if ($bundleId === '') {
            return [];
        }

        $manifestPath = storage_path('app/exports/bundles/' . $bundleId . '/' . self::MANIFEST_FILE);
        if (! File::exists($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) File::get($manifestPath), true);
        if (! is_array($decoded)) {
            return [];
        }

        $files = $decoded['files'] ?? [];
        if (! is_array($files)) {
            return [];
        }

        $out = [];
        foreach ($files as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $out[$key] = [
                'version' => max(0, (int) ($row['version'] ?? 0)),
                'sha256' => (string) ($row['sha256'] ?? ''),
            ];
        }

        return $out;
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
