<?php

namespace App\Console\Commands;

use App\Models\DailyDungeon;
use App\Models\DailyDungeonFirstClearReward;
use App\Models\DailyDungeonLevel;
use App\Models\DailyDungeonLevelMonster;
use App\Models\DailyDungeonUpgradeCost;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportDailyDungeonsJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/daily_dungeons_v1.json';

    protected $signature = 'game:export-daily-dungeons';

    protected $description = 'Export enabled daily dungeons to storage/app/exports/daily_dungeons_v1.json';

    public function handle(): int
    {
        $dailyDungeons = DailyDungeon::query()
            ->with([
                'levels' => fn ($query) => $query
                    ->where('is_enabled', true)
                    ->with([
                        'monsterEntries' => fn ($monsterQuery) => $monsterQuery
                            ->where('is_enabled', true)
                            ->orderBy('sort_order')
                            ->orderBy('id'),
                        'upgradeCosts' => fn ($costQuery) => $costQuery
                            ->where('is_enabled', true)
                            ->orderBy('sort_order')
                            ->orderBy('id'),
                        'firstClearRewards' => fn ($rewardQuery) => $rewardQuery
                            ->where('is_enabled', true)
                            ->orderBy('sort_order')
                            ->orderBy('id'),
                    ])
                    ->orderBy('level_no')
                    ->orderBy('id'),
            ])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('dungeon_id')
            ->get()
            ->map(function (DailyDungeon $dungeon): array {
                return [
                    'dungeon_id' => (string) $dungeon->dungeon_id,
                    'title' => (string) $dungeon->title,
                    'display_name' => (string) $dungeon->display_name,
                    'dungeon_type' => (string) $dungeon->dungeon_type,
                    'unlock_level' => (int) $dungeon->unlock_level,
                    'entry_cost_item_id' => $dungeon->entry_cost_item_id !== null ? (string) $dungeon->entry_cost_item_id : null,
                    'entry_cost_count' => (int) $dungeon->entry_cost_count,
                    'daily_limit' => (int) $dungeon->daily_limit,
                    'sweep_enabled' => (bool) $dungeon->sweep_enabled,
                    'icon' => $dungeon->icon !== null ? (string) $dungeon->icon : null,
                    'summary' => $dungeon->summary !== null ? (string) $dungeon->summary : null,
                    'sort_order' => (int) $dungeon->sort_order,
                    'is_enabled' => (bool) $dungeon->is_enabled,
                    'remark' => $dungeon->remark !== null ? (string) $dungeon->remark : null,
                    'levels' => $dungeon->levels->map(fn (DailyDungeonLevel $level): array => [
                        'dungeon_level_id' => (string) $level->dungeon_level_id,
                        'dungeon_id' => (string) $level->dungeon_id,
                        'level_no' => (int) $level->level_no,
                        'level_name' => (string) $level->level_name,
                        'recommended_level' => $level->recommended_level !== null ? (int) $level->recommended_level : null,
                        'recommended_power' => $level->recommended_power !== null ? (int) $level->recommended_power : null,
                        'is_max_level' => (bool) $level->is_max_level,
                        'summary' => $level->summary !== null ? (string) $level->summary : null,
                        'sort_order' => (int) $level->sort_order,
                        'is_enabled' => (bool) $level->is_enabled,
                        'remark' => $level->remark !== null ? (string) $level->remark : null,
                        'monster_entries' => $level->monsterEntries->map(fn (DailyDungeonLevelMonster $entry): array => [
                            'monster_id' => (string) $entry->monster_id,
                            'spawn_type' => (string) $entry->spawn_type,
                            'weight' => (int) $entry->weight,
                            'min_count' => (int) $entry->min_count,
                            'max_count' => (int) $entry->max_count,
                            'sort_order' => (int) $entry->sort_order,
                            'is_enabled' => (bool) $entry->is_enabled,
                            'remark' => $entry->remark !== null ? (string) $entry->remark : null,
                        ])->values()->all(),
                        'upgrade_costs' => $level->upgradeCosts->map(fn (DailyDungeonUpgradeCost $cost): array => [
                            'target_level_no' => (int) $cost->target_level_no,
                            'item_id' => (string) $cost->item_id,
                            'count' => (int) $cost->count,
                            'sort_order' => (int) $cost->sort_order,
                            'is_enabled' => (bool) $cost->is_enabled,
                            'remark' => $cost->remark !== null ? (string) $cost->remark : null,
                        ])->values()->all(),
                        'first_clear_rewards' => $level->firstClearRewards->map(fn (DailyDungeonFirstClearReward $reward): array => [
                            'item_id' => (string) $reward->item_id,
                            'count' => (int) $reward->count,
                            'sort_order' => (int) $reward->sort_order,
                            'is_enabled' => (bool) $reward->is_enabled,
                            'remark' => $reward->remark !== null ? (string) $reward->remark : null,
                        ])->values()->all(),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'daily_dungeons' => $dailyDungeons,
        ];
        $payload = ['meta' => ExportMetaService::makeMeta('daily_dungeons', $payloadWithoutMeta)] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('daily_dungeons_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'daily_dungeons_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d daily dungeons -> %s, %s', count($dailyDungeons), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
