<?php

namespace App\Console\Commands;

use App\Models\Monster;
use App\Models\MonsterBossProfile;
use App\Models\MonsterDropBinding;
use App\Models\MonsterSkillBinding;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportMonstersJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/monsters.json';

    protected $signature = 'game:export-monsters';

    protected $description = 'Export enabled monsters to storage/app/exports/monsters.json';

    public function handle(): int
    {
        $monsters = Monster::query()
            ->with([
                'skillBindings' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'dropBindings' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'bossProfile',
            ])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('monster_id')
            ->get()
            ->map(function (Monster $monster): array {
                return [
                    'id' => (int) $monster->id,
                    'monster_id' => (string) $monster->monster_id,
                    'monster_name' => (string) $monster->monster_name,
                    'display_name' => (string) $monster->display_name,
                    'monster_type' => (string) $monster->monster_type,
                    'chapter_id' => (string) $monster->chapter_id,
                    'display_stage_id' => $monster->display_stage_id !== null ? (string) $monster->display_stage_id : null,
                    'family' => $monster->family !== null ? (string) $monster->family : null,
                    'title' => $monster->title !== null ? (string) $monster->title : null,
                    'desc' => $monster->desc !== null ? (string) $monster->desc : null,
                    'icon' => $monster->icon !== null ? (string) $monster->icon : '',
                    'sprite' => $monster->sprite !== null ? (string) $monster->sprite : '',
                    'prefab_key' => $monster->prefab_key !== null ? (string) $monster->prefab_key : '',
                    'level' => (int) $monster->level,
                    'hp' => (int) $monster->hp,
                    'atk' => (int) $monster->atk,
                    'def' => (int) $monster->def,
                    'speed' => (int) $monster->speed,
                    'move_speed' => (int) $monster->move_speed,
                    'attack_range' => (int) $monster->attack_range,
                    'attack_interval' => (float) $monster->attack_interval,
                    'aggro_range' => (int) $monster->aggro_range,
                    'ai_type' => (string) $monster->ai_type,
                    'rarity_tag' => (string) $monster->rarity_tag,
                    'is_enabled' => (bool) $monster->is_enabled,
                    'sort_order' => (int) $monster->sort_order,
                    'remark' => $monster->remark !== null ? (string) $monster->remark : null,
                    'skill_bindings' => $monster->skillBindings->map(fn (MonsterSkillBinding $binding): array => [
                        'skill_id' => (string) $binding->skill_id,
                        'slot_type' => (string) $binding->slot_type,
                        'trigger_priority' => (int) $binding->trigger_priority,
                        'phase_limit' => $binding->phase_limit !== null ? (string) $binding->phase_limit : null,
                        'sort_order' => (int) $binding->sort_order,
                        'is_enabled' => (bool) $binding->is_enabled,
                        'remark' => $binding->remark !== null ? (string) $binding->remark : null,
                    ])->values()->all(),
                    'drop_bindings' => $monster->dropBindings->map(fn (MonsterDropBinding $binding): array => [
                        'drop_group_id' => (string) $binding->drop_group_id,
                        'is_primary' => (bool) $binding->is_primary,
                        'sort_order' => (int) $binding->sort_order,
                        'remark' => $binding->remark !== null ? (string) $binding->remark : null,
                    ])->values()->all(),
                    'boss_profile' => $monster->bossProfile instanceof MonsterBossProfile ? [
                        'phase_count' => (int) $monster->bossProfile->phase_count,
                        'phase_rules' => array_values(is_array($monster->bossProfile->phase_rules) ? $monster->bossProfile->phase_rules : []),
                        'summon_rules' => array_values(is_array($monster->bossProfile->summon_rules) ? $monster->bossProfile->summon_rules : []),
                        'rage_rules' => array_values(is_array($monster->bossProfile->rage_rules) ? $monster->bossProfile->rage_rules : []),
                        'weak_point_rules' => array_values(is_array($monster->bossProfile->weak_point_rules) ? $monster->bossProfile->weak_point_rules : []),
                        'intro_text' => $monster->bossProfile->intro_text,
                        'battle_bgm_id' => $monster->bossProfile->battle_bgm_id,
                        'camera_rule' => $monster->bossProfile->camera_rule,
                        'entry_fx_key' => $monster->bossProfile->entry_fx_key,
                        'death_fx_key' => $monster->bossProfile->death_fx_key,
                        'first_clear_reward_group_id' => $monster->bossProfile->first_clear_reward_group_id,
                        'story_flag_on_clear' => $monster->bossProfile->story_flag_on_clear,
                        'remark' => $monster->bossProfile->remark,
                    ] : null,
                ];
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'monsters' => $monsters,
        ];
        $payload = ['meta' => ExportMetaService::makeMeta('monsters', $payloadWithoutMeta)] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('monsters.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'monsters.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d monsters -> %s, %s', count($monsters), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
