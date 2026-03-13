<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Item;
use Illuminate\Validation\ValidationException;

class ProgressionMilestonesService
{
    public const SETTING_KEY = 'progression_milestones';

    /**
     * @var array<string, string>
     */
    private const UNLOCK_CONTENT_TYPE_OPTIONS = [
        'main_stage' => '主线关卡',
        'daily_dungeon' => '日常副本',
        'blue_gear' => '蓝装阶段',
        'feature_unlock' => '功能开放',
    ];

    public static function unlockContentTypeOptions(): array
    {
        return self::UNLOCK_CONTENT_TYPE_OPTIONS;
    }

    public static function defaultConfig(): array
    {
        return [
            'version' => 1,
            'range' => [
                'min_level' => 1,
                'max_level' => 20,
            ],
            'milestones' => [
                [
                    'level' => 1,
                    'milestone_key' => 'lv1_start',
                    'title' => '初入宗门',
                    'summary' => '完成新手引导，开始巡山。',
                    'image' => '',
                    'unlock_contents' => [
                        ['type' => 'main_stage', 'content' => '开放主线第1关'],
                        ['type' => 'feature_unlock', 'content' => '开放宗门任务'],
                        ['type' => 'blue_gear', 'content' => '开放1级蓝装阶段'],
                    ],
                    'reward_item_id' => 'milestone_pack_lv1',
                    'reward_count' => 1,
                    'claim_once' => true,
                    'is_enabled' => true,
                    'sort' => 10,
                ],
                [
                    'level' => 5,
                    'milestone_key' => 'lv5_early_growth',
                    'title' => '初步成型',
                    'summary' => '蓝装进入5级档，主线进入稳定推进。',
                    'image' => '',
                    'unlock_contents' => [
                        ['type' => 'main_stage', 'content' => '开放主线第2关'],
                        ['type' => 'daily_dungeon', 'content' => '开放金币副本'],
                        ['type' => 'blue_gear', 'content' => '蓝装进入5级档'],
                    ],
                    'reward_item_id' => 'milestone_pack_lv5',
                    'reward_count' => 1,
                    'claim_once' => true,
                    'is_enabled' => true,
                    'sort' => 20,
                ],
                [
                    'level' => 10,
                    'milestone_key' => 'lv10_stable_push',
                    'title' => '稳定推进',
                    'summary' => '主线进入第一轮稳定期，开始为后续双词条阶段做准备。',
                    'image' => '',
                    'unlock_contents' => [
                        ['type' => 'main_stage', 'content' => '开放主线第3关'],
                        ['type' => 'daily_dungeon', 'content' => '开放经验副本'],
                        ['type' => 'blue_gear', 'content' => '继续使用10级蓝装'],
                    ],
                    'reward_item_id' => 'milestone_pack_lv10',
                    'reward_count' => 1,
                    'claim_once' => true,
                    'is_enabled' => true,
                    'sort' => 30,
                ],
                [
                    'level' => 15,
                    'milestone_key' => 'lv15_affix_upgrade',
                    'title' => '蓝装强化',
                    'summary' => '蓝装进入双词条阶段，开始形成明显构筑差异。',
                    'image' => '',
                    'unlock_contents' => [
                        ['type' => 'main_stage', 'content' => '开放主线第4关'],
                        ['type' => 'daily_dungeon', 'content' => '开放材料副本'],
                        ['type' => 'blue_gear', 'content' => '蓝装进入15级双词条阶段'],
                    ],
                    'reward_item_id' => 'milestone_pack_lv15',
                    'reward_count' => 1,
                    'claim_once' => true,
                    'is_enabled' => true,
                    'sort' => 40,
                ],
                [
                    'level' => 20,
                    'milestone_key' => 'lv20_mid_ready',
                    'title' => '迈入中期',
                    'summary' => '20级蓝装完整成型，准备进入20-40阶段。',
                    'image' => '',
                    'unlock_contents' => [
                        ['type' => 'main_stage', 'content' => '开放主线第5关'],
                        ['type' => 'daily_dungeon', 'content' => '开放宝石副本'],
                        ['type' => 'blue_gear', 'content' => '蓝装进入20级双词条阶段'],
                    ],
                    'reward_item_id' => 'milestone_pack_lv20',
                    'reward_count' => 1,
                    'claim_once' => true,
                    'is_enabled' => true,
                    'sort' => 50,
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

        $version = max(1, (int) ($normalized['version'] ?? 1));
        $range = is_array($normalized['range'] ?? null) ? $normalized['range'] : [];
        $minLevel = (int) ($range['min_level'] ?? 1);
        $maxLevel = (int) ($range['max_level'] ?? 20);

        if ($minLevel !== 1) {
            $errors['range.min_level'] = '当前 V1 的起始等级固定为 1。';
        }

        if ($maxLevel !== 20) {
            $errors['range.max_level'] = '当前 V1 的结束等级固定为 20。';
        }

        $milestones = self::normalizeMilestones($normalized['milestones'] ?? [], $minLevel, $maxLevel, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'version' => $version,
            'range' => [
                'min_level' => $minLevel,
                'max_level' => $maxLevel,
            ],
            'milestones' => $milestones,
        ];
    }

    /**
     * @param  mixed  $rows
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeMilestones(mixed $rows, int $minLevel, int $maxLevel, array &$errors): array
    {
        $normalized = [];
        $seenLevels = [];
        $seenKeys = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $level = (int) ($row['level'] ?? 0);
            $milestoneKey = trim((string) ($row['milestone_key'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $summary = trim((string) ($row['summary'] ?? ''));
            $image = trim((string) ($row['image'] ?? ''));
            $rewardItemId = trim((string) ($row['reward_item_id'] ?? ''));
            $rewardCount = max(1, (int) ($row['reward_count'] ?? 1));
            $claimOnce = (bool) ($row['claim_once'] ?? true);
            $isEnabled = (bool) ($row['is_enabled'] ?? true);
            $sort = max(0, (int) ($row['sort'] ?? (($index + 1) * 10)));

            if ($level < $minLevel || $level > $maxLevel) {
                $errors["milestones.{$index}.level"] = sprintf('里程碑等级必须位于 %d-%d 之间。', $minLevel, $maxLevel);
            } elseif (isset($seenLevels[$level])) {
                $errors["milestones.{$index}.level"] = sprintf('里程碑等级重复：%d。', $level);
            } else {
                $seenLevels[$level] = true;
            }

            if ($milestoneKey === '') {
                $errors["milestones.{$index}.milestone_key"] = '里程碑标识不能为空。';
            } elseif (isset($seenKeys[$milestoneKey])) {
                $errors["milestones.{$index}.milestone_key"] = sprintf('里程碑标识重复：%s。', $milestoneKey);
            } else {
                $seenKeys[$milestoneKey] = true;
            }

            if ($title === '') {
                $errors["milestones.{$index}.title"] = '标题不能为空。';
            }

            if ($summary === '') {
                $errors["milestones.{$index}.summary"] = '简述不能为空。';
            }

            if ($rewardItemId === '') {
                $errors["milestones.{$index}.reward_item_id"] = '请选择里程碑奖励物品。';
            } elseif (! Item::query()->where('item_id', $rewardItemId)->where('is_enabled', true)->exists()) {
                $errors["milestones.{$index}.reward_item_id"] = '里程碑奖励物品无效。';
            }

            if ($rewardCount < 1) {
                $errors["milestones.{$index}.reward_count"] = '奖励数量必须大于等于 1。';
            }

            $unlockContents = self::normalizeUnlockContents($row['unlock_contents'] ?? [], "milestones.{$index}.unlock_contents", $errors);

            $normalized[] = [
                'level' => $level,
                'milestone_key' => $milestoneKey,
                'title' => $title,
                'summary' => $summary,
                'image' => $image,
                'unlock_contents' => $unlockContents,
                'reward_item_id' => $rewardItemId,
                'reward_count' => $rewardCount,
                'claim_once' => $claimOnce,
                'is_enabled' => $isEnabled,
                'sort' => $sort,
            ];
        }

        usort($normalized, function (array $a, array $b): int {
            $levelCompare = ($a['level'] ?? 0) <=> ($b['level'] ?? 0);
            if ($levelCompare !== 0) {
                return $levelCompare;
            }

            return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
        });

        return array_values($normalized);
    }

    /**
     * @param  mixed  $rows
     * @param  array<string, string>  $errors
     * @return array<int, array{type:string, content:string}>
     */
    private static function normalizeUnlockContents(mixed $rows, string $field, array &$errors): array
    {
        $normalized = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = trim((string) ($row['type'] ?? ''));
            $content = trim((string) ($row['content'] ?? ''));

            if (! array_key_exists($type, self::UNLOCK_CONTENT_TYPE_OPTIONS)) {
                $errors["{$field}.{$index}.type"] = '开放内容类型无效。';
            }

            if ($content === '') {
                $errors["{$field}.{$index}.content"] = '开放内容不能为空。';
            }

            $normalized[] = [
                'type' => $type,
                'content' => $content,
            ];
        }

        return array_values($normalized);
    }

    private static function mergeRecursive(array $defaults, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && self::isAssociative($value) && self::isAssociative($defaults[$key])) {
                $defaults[$key] = self::mergeRecursive($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    private static function isAssociative(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
