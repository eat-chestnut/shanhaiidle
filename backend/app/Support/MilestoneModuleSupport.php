<?php

namespace App\Support;

use App\Models\Item;
use App\Models\MainStageChapter;
use App\Models\Milestone;
use Illuminate\Validation\ValidationException;

class MilestoneModuleSupport
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $conditionType = trim((string) ($row['condition_type'] ?? 'player_level_reached'));
        $normalizedConditionValue = match ($conditionType) {
            'player_level_reached' => filled($row['condition_value'] ?? null)
                ? (string) max(1, (int) $row['condition_value'])
                : '',
            default => trim((string) ($row['condition_value'] ?? '')),
        };

        return [
            'milestone_id' => trim((string) ($row['milestone_id'] ?? '')),
            'title' => trim((string) ($row['title'] ?? '')),
            'display_name' => trim((string) ($row['display_name'] ?? '')),
            'condition_type' => $conditionType,
            'condition_value' => $normalizedConditionValue,
            'pre_milestone_id' => filled($row['pre_milestone_id'] ?? null)
                ? trim((string) $row['pre_milestone_id'])
                : null,
            'reward_item_id' => trim((string) ($row['reward_item_id'] ?? '')),
            'reward_count' => max(1, (int) ($row['reward_count'] ?? 1)),
            'icon' => filled($row['icon'] ?? null) ? trim((string) $row['icon']) : null,
            'summary' => trim((string) ($row['summary'] ?? '')),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function validateRowOrFail(array $row, ?string $currentMilestoneId = null): void
    {
        $normalized = self::normalizeRow($row);
        $lookup = Milestone::query()
            ->get([
                'milestone_id',
                'pre_milestone_id',
                'condition_type',
                'condition_value',
                'is_enabled',
            ])
            ->mapWithKeys(fn (Milestone $milestone): array => [
                (string) $milestone->milestone_id => [
                    'milestone_id' => (string) $milestone->milestone_id,
                    'pre_milestone_id' => $milestone->pre_milestone_id !== null ? (string) $milestone->pre_milestone_id : null,
                ],
            ])
            ->all();

        $existing = $lookup[$normalized['milestone_id']] ?? null;
        if ($existing !== null && $normalized['milestone_id'] !== $currentMilestoneId) {
            throw ValidationException::withMessages([
                'milestone_id' => '里程碑 ID 已存在。',
            ]);
        }

        if ($normalized['milestone_id'] !== '') {
            $lookup[$normalized['milestone_id']] = [
                'milestone_id' => $normalized['milestone_id'],
                'pre_milestone_id' => $normalized['pre_milestone_id'],
            ];
        }

        $errors = [];
        self::validateNormalizedRow($errors, '', $normalized, $lookup);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function validateRowsOrFail(array $rows): void
    {
        $errors = [];
        $normalizedRows = [];
        $lookup = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["milestones.{$index}"] = '里程碑条目格式错误。';

                continue;
            }

            $normalized = self::normalizeRow($row);
            $normalizedRows[] = $normalized;

            if ($normalized['milestone_id'] === '') {
                $errors["milestones.{$index}.milestone_id"] = '请填写 milestone_id。';

                continue;
            }

            if (isset($lookup[$normalized['milestone_id']])) {
                $errors["milestones.{$index}.milestone_id"] = sprintf('milestone_id 重复：%s。', $normalized['milestone_id']);

                continue;
            }

            $lookup[$normalized['milestone_id']] = [
                'milestone_id' => $normalized['milestone_id'],
                'pre_milestone_id' => $normalized['pre_milestone_id'],
            ];
        }

        foreach ($normalizedRows as $index => $row) {
            self::validateNormalizedRow($errors, "milestones.{$index}.", $row, $lookup);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return int|string
     */
    public static function exportConditionValue(Milestone $milestone): int|string
    {
        return (string) $milestone->condition_type === 'player_level_reached'
            ? max(1, (int) $milestone->condition_value)
            : (string) $milestone->condition_value;
    }

    /**
     * @param  array<string, string>  $errors
     * @param  array<string, array{milestone_id:string,pre_milestone_id:?string}>  $lookup
     * @param  array<string, mixed>  $row
     */
    private static function validateNormalizedRow(array &$errors, string $prefix, array $row, array $lookup): void
    {
        if ($row['milestone_id'] === '') {
            $errors["{$prefix}milestone_id"] = '请填写里程碑 ID。';
        }

        if ($row['title'] === '') {
            $errors["{$prefix}title"] = '请填写内部名称。';
        }

        if ($row['display_name'] === '') {
            $errors["{$prefix}display_name"] = '请填写展示名称。';
        }

        if ($row['summary'] === '') {
            $errors["{$prefix}summary"] = '请填写里程碑描述。';
        }

        if (! isset(Milestone::CONDITION_TYPE_OPTIONS[$row['condition_type']])) {
            $errors["{$prefix}condition_type"] = '条件类型非法。';
        } elseif ((string) $row['condition_type'] === 'player_level_reached') {
            $conditionLevel = (int) ($row['condition_value'] ?? 0);
            if ($conditionLevel < 1) {
                $errors["{$prefix}condition_value"] = '等级条件必须是大于等于 1 的整数。';
            }
        } else {
            $chapterId = trim((string) ($row['condition_value'] ?? ''));
            if ($chapterId === '') {
                $errors["{$prefix}condition_value"] = '章节条件不能为空。';
            } elseif (! MainStageChapter::query()
                ->where('chapter_id', $chapterId)
                ->where('is_enabled', true)
                ->where('has_combat', true)
                ->exists()) {
                $errors["{$prefix}condition_value"] = '章节条件必须引用已启用的主线战斗章节 ID。';
            }
        }

        $rewardItemId = trim((string) ($row['reward_item_id'] ?? ''));
        if ($rewardItemId === '') {
            $errors["{$prefix}reward_item_id"] = '请选择奖励物品。';
        } elseif (! Item::query()->where('item_id', $rewardItemId)->where('is_enabled', true)->exists()) {
            $errors["{$prefix}reward_item_id"] = '奖励物品不存在或未启用。';
        }

        if ((int) ($row['reward_count'] ?? 0) < 1) {
            $errors["{$prefix}reward_count"] = '奖励数量必须大于等于 1。';
        }

        $preMilestoneId = trim((string) ($row['pre_milestone_id'] ?? ''));
        if ($preMilestoneId !== '') {
            if ($preMilestoneId === (string) ($row['milestone_id'] ?? '')) {
                $errors["{$prefix}pre_milestone_id"] = '前置节点不能指向自己。';
            } elseif (! isset($lookup[$preMilestoneId])) {
                $errors["{$prefix}pre_milestone_id"] = '前置节点不存在。';
            } elseif (self::hasCircularReference((string) ($row['milestone_id'] ?? ''), $lookup)) {
                $errors["{$prefix}pre_milestone_id"] = '前置节点形成了循环依赖。';
            }
        }
    }

    /**
     * @param  array<string, array{milestone_id:string,pre_milestone_id:?string}>  $lookup
     */
    private static function hasCircularReference(string $milestoneId, array $lookup): bool
    {
        if ($milestoneId === '' || ! isset($lookup[$milestoneId])) {
            return false;
        }

        $visited = [];
        $cursor = $milestoneId;

        while ($cursor !== '' && isset($lookup[$cursor])) {
            if (isset($visited[$cursor])) {
                return true;
            }

            $visited[$cursor] = true;
            $next = trim((string) ($lookup[$cursor]['pre_milestone_id'] ?? ''));
            if ($next === '') {
                return false;
            }

            $cursor = $next;
        }

        return false;
    }
}
