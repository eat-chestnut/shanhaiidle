<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Item;
use App\Models\Stage;
use Illuminate\Validation\ValidationException;

class SectTaskRulesService
{
    public const SETTING_KEY = 'sect_task_rules';

    /**
     * @var array<string, string>
     */
    private const GOAL_TYPE_OPTIONS = [
        'visit_sect' => '回宗报到',
        'clear_stage' => '主线通关',
        'complete_dungeon' => '完成日常副本',
        'forge' => '整备法器',
        'kill_normal' => '清剿异兽',
        'kill_boss' => '讨伐大妖',
        'collect_loot' => '收取历练所得',
        'spend_stamina' => '消耗体力',
    ];

    public static function goalTypeOptions(): array
    {
        return self::GOAL_TYPE_OPTIONS;
    }

    public static function defaultConfig(): array
    {
        return [
            'daily_tasks' => [
                [
                    'task_id' => 'sect_check_in',
                    'name' => '回宗报到',
                    'desc' => '回到宗门主界面 1 次。',
                    'goal_type' => 'visit_sect',
                    'target' => 1,
                    'unlock_stage_id' => 'nan_01',
                    'rewards' => ['gold' => 80, 'sect_contribution' => 20, 'skill_points' => 0, 'items' => []],
                    'sort_order' => 10,
                    'is_enabled' => true,
                ],
                [
                    'task_id' => 'sect_patrol',
                    'name' => '巡山历练',
                    'desc' => '击杀 20 只普通异兽。',
                    'goal_type' => 'kill_normal',
                    'target' => 20,
                    'unlock_stage_id' => 'nan_01',
                    'rewards' => ['gold' => 100, 'sect_contribution' => 20, 'skill_points' => 0, 'items' => []],
                    'sort_order' => 20,
                    'is_enabled' => true,
                ],
                [
                    'task_id' => 'sect_daily_drill',
                    'name' => '完成任意日常副本',
                    'desc' => '完成 1 次演武场日常副本。',
                    'goal_type' => 'complete_dungeon',
                    'target' => 1,
                    'unlock_stage_id' => 'nan_02',
                    'rewards' => ['gold' => 120, 'sect_contribution' => 25, 'skill_points' => 0, 'items' => []],
                    'sort_order' => 30,
                    'is_enabled' => true,
                ],
                [
                    'task_id' => 'sect_forge_once',
                    'name' => '整备法器',
                    'desc' => '完成 1 次打造或升品。',
                    'goal_type' => 'forge',
                    'target' => 1,
                    'unlock_stage_id' => 'nan_02',
                    'rewards' => ['gold' => 140, 'sect_contribution' => 25, 'skill_points' => 0, 'items' => []],
                    'sort_order' => 40,
                    'is_enabled' => true,
                ],
                [
                    'task_id' => 'sect_spend_stamina',
                    'name' => '消耗体力',
                    'desc' => '累计消耗 20 点体力。',
                    'goal_type' => 'spend_stamina',
                    'target' => 20,
                    'unlock_stage_id' => 'nan_02',
                    'rewards' => ['gold' => 150, 'sect_contribution' => 30, 'skill_points' => 0, 'items' => []],
                    'sort_order' => 50,
                    'is_enabled' => true,
                ],
            ],
            'milestone_tasks' => [
                [
                    'task_id' => 'sect_clear_stage',
                    'name' => '山门开路',
                    'desc' => '首次通关任意主线关卡。',
                    'goal_type' => 'clear_stage',
                    'target' => 1,
                    'unlock_stage_id' => 'nan_01',
                    'rewards' => ['gold' => 180, 'sect_contribution' => 35, 'skill_points' => 1, 'items' => []],
                    'sort_order' => 10,
                    'is_enabled' => true,
                ],
                [
                    'task_id' => 'sect_boss_hunt',
                    'name' => '讨伐大妖',
                    'desc' => '击败 1 只 Boss。',
                    'goal_type' => 'kill_boss',
                    'target' => 1,
                    'unlock_stage_id' => 'nan_03',
                    'rewards' => ['gold' => 220, 'sect_contribution' => 40, 'skill_points' => 1, 'items' => []],
                    'sort_order' => 20,
                    'is_enabled' => true,
                ],
                [
                    'task_id' => 'sect_collect_loot',
                    'name' => '收取历练所得',
                    'desc' => '累计获得 15 次掉落。',
                    'goal_type' => 'collect_loot',
                    'target' => 15,
                    'unlock_stage_id' => 'nan_01',
                    'rewards' => ['gold' => 160, 'sect_contribution' => 25, 'skill_points' => 0, 'items' => [
                        ['item_id' => '宗门令', 'count' => 1],
                    ]],
                    'sort_order' => 30,
                    'is_enabled' => true,
                ],
            ],
        ];
    }

    public static function ensureDefaultSetting(): void
    {
        if (AppSetting::query()->where('key', self::SETTING_KEY)->exists()) {
            return;
        }

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode(self::defaultConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function loadConfig(): array
    {
        $raw = AppSetting::getValue(self::SETTING_KEY);
        if ($raw === null || trim($raw) === '') {
            return self::defaultConfig();
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return self::defaultConfig();
        }

        return self::mergeRecursive(self::defaultConfig(), $decoded);
    }

    public static function saveConfig(array $config): void
    {
        $normalized = self::validateAndNormalizeConfig($config);

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function exportConfig(): array
    {
        return self::validateAndNormalizeConfig(self::loadConfig());
    }

    private static function validateAndNormalizeConfig(array $config): array
    {
        $normalized = self::mergeRecursive(self::defaultConfig(), $config);
        $errors = [];

        $normalized['daily_tasks'] = self::normalizeTaskRows($normalized['daily_tasks'] ?? [], 'daily_tasks', $errors);
        $normalized['milestone_tasks'] = self::normalizeTaskRows($normalized['milestone_tasks'] ?? [], 'milestone_tasks', $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeTaskRows(mixed $rows, string $field, array &$errors): array
    {
        $normalized = [];
        $seen = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $taskId = trim((string) ($row['task_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $desc = trim((string) ($row['desc'] ?? ''));
            $goalType = trim((string) ($row['goal_type'] ?? ''));
            $unlockStageId = trim((string) ($row['unlock_stage_id'] ?? ''));
            $target = $row['target'] ?? null;

            if ($taskId === '') {
                $errors["{$field}.{$index}.task_id"] = '任务 ID 不能为空。';
            } elseif (isset($seen[$taskId])) {
                $errors["{$field}.{$index}.task_id"] = sprintf('任务 ID 重复：%s。', $taskId);
            } else {
                $seen[$taskId] = true;
            }

            if ($name === '') {
                $errors["{$field}.{$index}.name"] = '任务名称不能为空。';
            }

            if (! array_key_exists($goalType, self::GOAL_TYPE_OPTIONS)) {
                $errors["{$field}.{$index}.goal_type"] = '任务目标类型无效。';
            }

            if (! is_numeric($target) || (int) $target < 1) {
                $errors["{$field}.{$index}.target"] = '目标次数必须大于等于 1。';
            }

            if ($unlockStageId !== '' && ! Stage::query()->where('id', $unlockStageId)->where('is_enabled', true)->exists()) {
                $errors["{$field}.{$index}.unlock_stage_id"] = '主线解锁关卡无效。';
            }

            $rewards = self::normalizeRewards($row['rewards'] ?? [], "{$field}.{$index}.rewards", $errors);

            $normalized[] = [
                'task_id' => $taskId,
                'name' => $name,
                'desc' => $desc,
                'goal_type' => $goalType,
                'target' => (int) $target,
                'unlock_stage_id' => $unlockStageId !== '' ? $unlockStageId : null,
                'rewards' => $rewards,
                'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeRewards(mixed $value, string $field, array &$errors): array
    {
        $row = is_array($value) ? $value : [];
        $items = [];

        foreach (is_array($row['items'] ?? null) ? $row['items'] : [] as $index => $itemRow) {
            if (! is_array($itemRow)) {
                continue;
            }

            $itemId = trim((string) ($itemRow['item_id'] ?? ''));
            $count = $itemRow['count'] ?? null;

            if ($itemId === '') {
                $errors["{$field}.items.{$index}.item_id"] = '奖励物品不能为空。';
                continue;
            }

            if (! Item::query()->where('item_id', $itemId)->where('is_enabled', true)->exists()) {
                $errors["{$field}.items.{$index}.item_id"] = '奖励物品无效。';
                continue;
            }

            if (! is_numeric($count) || (int) $count < 1) {
                $errors["{$field}.items.{$index}.count"] = '奖励数量必须大于等于 1。';
                continue;
            }

            $items[] = [
                'item_id' => $itemId,
                'count' => (int) $count,
            ];
        }

        return [
            'gold' => max(0, (int) ($row['gold'] ?? 0)),
            'spirit_stone' => max(0, (int) ($row['spirit_stone'] ?? 0)),
            'sect_contribution' => max(0, (int) ($row['sect_contribution'] ?? 0)),
            'skill_points' => max(0, (int) ($row['skill_points'] ?? 0)),
            'items' => $items,
        ];
    }

    private static function mergeRecursive(array $defaults, array $override): array
    {
        $result = $defaults;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key]) && self::isAssoc($value) && self::isAssoc($result[$key])) {
                $result[$key] = self::mergeRecursive($result[$key], $value);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private static function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
