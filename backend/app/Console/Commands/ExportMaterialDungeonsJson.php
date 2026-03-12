<?php

namespace App\Console\Commands;

use App\Models\MaterialDungeon;
use App\Models\MaterialDungeonDropGroup;
use App\Services\ExportMetaService;
use App\Support\AdminOptions;
use App\Support\MaterialDungeonSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportMaterialDungeonsJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/material_dungeons_v1.json';

    protected $signature = 'game:export-material-dungeons';

    protected $description = 'Export enabled material dungeons to storage/app/exports/material_dungeons_v1.json';

    public function handle(): int
    {
        $rows = MaterialDungeon::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('dungeon_id')
            ->get();

        $materialDungeons = $rows
            ->map(fn (MaterialDungeon $row): array => [
                'dungeon_id' => (string) $row->dungeon_id,
                'name' => (string) $row->name,
                'dungeon_type' => (string) $row->dungeon_type,
                'dungeon_type_name' => AdminOptions::optionLabel(AdminOptions::dungeonTypeOptions(), (string) $row->dungeon_type),
                'unlock_level' => (int) $row->unlock_level,
                'unlock_stage_id' => filled($row->unlock_stage_id) ? (string) $row->unlock_stage_id : null,
                'unlock_stage_name' => filled($row->unlock_stage_id) ? AdminOptions::optionLabel(AdminOptions::stageOptions(), (string) $row->unlock_stage_id) : null,
                'display_rewards' => is_array($row->display_rewards) ? array_values($row->display_rewards) : [],
                'display_reward_details' => MaterialDungeonSupport::displayRewardDetails(is_array($row->display_rewards) ? $row->display_rewards : []),
                'layer_rules' => collect(is_array($row->layer_rules) ? $row->layer_rules : [])
                    ->map(fn (array $rule): array => [
                        'layer' => (int) ($rule['layer'] ?? 1),
                        'drop_group_id' => (string) ($rule['drop_group_id'] ?? ''),
                        'drop_group_name' => AdminOptions::materialDungeonDropGroupName((string) ($rule['drop_group_id'] ?? '')),
                        'first_clear_reward_group_id' => filled($rule['first_clear_reward_group_id'] ?? null) ? (string) $rule['first_clear_reward_group_id'] : null,
                        'first_clear_reward_group_name' => filled($rule['first_clear_reward_group_id'] ?? null)
                            ? AdminOptions::materialDungeonDropGroupName((string) $rule['first_clear_reward_group_id'])
                            : null,
                        'recommended_power' => filled($rule['recommended_power'] ?? null) ? (int) $rule['recommended_power'] : null,
                        'sort' => (int) ($rule['sort'] ?? 0),
                    ])
                    ->values()
                    ->all(),
                'level_configs' => collect(is_array($row->level_configs) ? $row->level_configs : [])
                    ->map(fn (array $config): array => [
                        'level' => (int) ($config['level'] ?? 1),
                        'reward_multiplier' => (float) ($config['reward_multiplier'] ?? 1),
                        'upgrade_costs' => collect(is_array($config['upgrade_costs'] ?? null) ? $config['upgrade_costs'] : [])
                            ->map(fn (array $cost): array => [
                                'item_id' => (string) ($cost['item_id'] ?? ''),
                                'item_name' => MaterialDungeonSupport::itemName((string) ($cost['item_id'] ?? '')),
                                'count' => (int) ($cost['count'] ?? 0),
                                'sort' => (int) ($cost['sort'] ?? 0),
                            ])
                            ->values()
                            ->all(),
                        'sort' => (int) ($config['sort'] ?? 0),
                    ])
                    ->values()
                    ->all(),
                'stamina_cost' => (int) $row->stamina_cost,
                'daily_limit' => (int) $row->daily_limit,
                'description' => (string) ($row->description ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        $dropGroups = MaterialDungeonDropGroup::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MaterialDungeonDropGroup $row): array => [
                'group_id' => (string) $row->group_id,
                'name' => (string) $row->name,
                'rewards' => MaterialDungeonSupport::exportDropGroupRewards(is_array($row->rewards) ? $row->rewards : []),
                'description' => (string) ($row->description ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'material_dungeons' => $materialDungeons,
            'material_dungeon_drop_groups' => $dropGroups,
        ];
        $meta = ExportMetaService::makeMeta('material_dungeons', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('material_dungeons_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'material_dungeons_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d material dungeons, %d drop groups -> %s, %s', count($materialDungeons), count($dropGroups), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
