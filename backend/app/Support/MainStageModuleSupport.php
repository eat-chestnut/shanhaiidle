<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Validation\ValidationException;

class MainStageModuleSupport
{
    public static function chapterTypeOptions(): array
    {
        return [
            'prologue' => '序章',
            'main' => '正式主线章',
            'epilogue' => '终章',
        ];
    }

    public static function chapterFlowTypeOptions(): array
    {
        return [
            'story_intro' => '开场叙事',
            'combat' => '战斗推进',
            'story_outro' => '结尾叙事',
        ];
    }

    public static function difficultyCodeOptions(): array
    {
        return [
            'difficulty_1' => '难度位 1',
            'difficulty_2' => '难度位 2',
            'difficulty_3' => '难度位 3',
        ];
    }

    public static function normalizeChapter(array $row): array
    {
        return [
            'chapter_id' => trim((string) ($row['chapter_id'] ?? '')),
            'chapter_name' => trim((string) ($row['chapter_name'] ?? '')),
            'chapter_type' => trim((string) ($row['chapter_type'] ?? 'main')),
            'chapter_flow_type' => trim((string) ($row['chapter_flow_type'] ?? 'combat')),
            'is_functional_chapter' => (bool) ($row['is_functional_chapter'] ?? false),
            'has_combat' => (bool) ($row['has_combat'] ?? false),
            'has_sect_selection' => (bool) ($row['has_sect_selection'] ?? false),
            'has_shanshen_ritual' => (bool) ($row['has_shanshen_ritual'] ?? false),
            'suggested_level_min' => max(1, (int) ($row['suggested_level_min'] ?? 1)),
            'suggested_level_max' => max(1, (int) ($row['suggested_level_max'] ?? 1)),
            'suggested_power' => max(0, (int) ($row['suggested_power'] ?? 0)),
            'mountain_name' => trim((string) ($row['mountain_name'] ?? '')),
            'boss_display_name' => trim((string) ($row['boss_display_name'] ?? '')),
            'unlock_level' => max(1, (int) ($row['unlock_level'] ?? 1)),
            'unlock_prev_chapter_id' => filled($row['unlock_prev_chapter_id'] ?? null) ? trim((string) $row['unlock_prev_chapter_id']) : null,
            'sect_selection_enabled' => (bool) ($row['sect_selection_enabled'] ?? ($row['has_sect_selection'] ?? false)),
            'sect_selection_pool_id' => filled($row['sect_selection_pool_id'] ?? null) ? trim((string) $row['sect_selection_pool_id']) : null,
            'shanshen_ritual_enabled' => (bool) ($row['shanshen_ritual_enabled'] ?? ($row['has_shanshen_ritual'] ?? false)),
            'next_version_teaser_title' => filled($row['next_version_teaser_title'] ?? null) ? trim((string) $row['next_version_teaser_title']) : null,
            'next_world_key' => filled($row['next_world_key'] ?? null) ? trim((string) $row['next_world_key']) : null,
            'teaser_desc' => filled($row['teaser_desc'] ?? null) ? trim((string) $row['teaser_desc']) : null,
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
        ];
    }

    public static function normalizeDifficulty(array $row, string $chapterId): array
    {
        return [
            'difficulty_id' => trim((string) ($row['difficulty_id'] ?? '')),
            'chapter_id' => $chapterId,
            'difficulty_code' => trim((string) ($row['difficulty_code'] ?? 'difficulty_1')),
            'difficulty_name' => trim((string) ($row['difficulty_name'] ?? '')),
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
        ];
    }

    public static function normalizeFirstClearRewards(mixed $raw): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $rows[] = [
                'item_id' => $itemId,
                'count' => max(1, (int) ($row['count'] ?? 1)),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? ($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        return array_values($rows);
    }

    public static function validateFirstClearRewardsOrFail(array $rows): void
    {
        $errors = [];
        $validItemIds = Item::query()->pluck('id')->all();
        $validItemLookup = array_fill_keys($validItemIds, true);

        foreach ($rows as $index => $row) {
            $itemId = trim((string) ($row['item_id'] ?? ''));
            if ($itemId === '') {
                $errors["first_clear_rewards.{$index}.item_id"] = '请选择奖励物品。';
            } elseif (! isset($validItemLookup[$itemId])) {
                $errors["first_clear_rewards.{$index}.item_id"] = '奖励物品不存在。';
            }

            if ((int) ($row['count'] ?? 0) < 1) {
                $errors["first_clear_rewards.{$index}.count"] = '奖励数量必须大于等于 1。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $chapters
     * @throws ValidationException
     */
    public static function validateChapterRowsOrFail(array $chapters): void
    {
        $errors = [];
        $seenChapterIds = [];

        foreach ($chapters as $index => $chapter) {
            $row = self::normalizeChapter($chapter);
            $chapterId = $row['chapter_id'];

            if ($chapterId === '') {
                $errors["chapters.{$index}.chapter_id"] = '请填写章节 ID。';
            } elseif (isset($seenChapterIds[$chapterId])) {
                $errors["chapters.{$index}.chapter_id"] = sprintf('章节 ID 重复：%s。', $chapterId);
            } else {
                $seenChapterIds[$chapterId] = true;
            }

            if ($row['chapter_name'] === '') {
                $errors["chapters.{$index}.chapter_name"] = '请填写章节名称。';
            }

            if (! isset(self::chapterTypeOptions()[$row['chapter_type']])) {
                $errors["chapters.{$index}.chapter_type"] = '章节类型非法。';
            }

            if (! isset(self::chapterFlowTypeOptions()[$row['chapter_flow_type']])) {
                $errors["chapters.{$index}.chapter_flow_type"] = '章节流程类型非法。';
            }

            if ($row['suggested_level_max'] < $row['suggested_level_min']) {
                $errors["chapters.{$index}.suggested_level_max"] = '建议等级上限不能小于下限。';
            }

            $difficulties = array_values(is_array($chapter['difficulties'] ?? null) ? $chapter['difficulties'] : []);
            if ($row['has_combat']) {
                if (count($difficulties) !== 3) {
                    $errors["chapters.{$index}.difficulties"] = '正式主线章必须配置 3 个难度。';
                }
            } elseif ($difficulties !== []) {
                $errors["chapters.{$index}.difficulties"] = '功能章节不能挂载战斗难度。';
            }

            $seenCodes = [];
            foreach ($difficulties as $difficultyIndex => $difficulty) {
                if (! is_array($difficulty)) {
                    $errors["chapters.{$index}.difficulties.{$difficultyIndex}"] = '难度配置格式错误。';
                    continue;
                }

                $difficultyRow = self::normalizeDifficulty($difficulty, $chapterId);
                if ($difficultyRow['difficulty_id'] === '') {
                    $errors["chapters.{$index}.difficulties.{$difficultyIndex}.difficulty_id"] = '请填写难度 ID。';
                }

                if (! isset(self::difficultyCodeOptions()[$difficultyRow['difficulty_code']])) {
                    $errors["chapters.{$index}.difficulties.{$difficultyIndex}.difficulty_code"] = '难度编码非法。';
                } elseif (isset($seenCodes[$difficultyRow['difficulty_code']])) {
                    $errors["chapters.{$index}.difficulties.{$difficultyIndex}.difficulty_code"] = sprintf('难度编码重复：%s。', $difficultyRow['difficulty_code']);
                } else {
                    $seenCodes[$difficultyRow['difficulty_code']] = true;
                }

                if ($difficultyRow['difficulty_name'] === '') {
                    $errors["chapters.{$index}.difficulties.{$difficultyIndex}.difficulty_name"] = '请填写难度名称。';
                }

                $monsterEntries = array_values(is_array($difficulty['monster_entries'] ?? null) ? $difficulty['monster_entries'] : []);
                if ($row['has_combat'] && $monsterEntries === []) {
                    $errors["chapters.{$index}.difficulties.{$difficultyIndex}.monster_entries"] = '战斗章节的每个难度都必须配置怪物列表。';
                    continue;
                }

                $firstClearRewards = self::normalizeFirstClearRewards($difficulty['first_clear_rewards'] ?? []);
                if ($row['has_combat'] && $firstClearRewards === []) {
                    $errors["chapters.{$index}.difficulties.{$difficultyIndex}.first_clear_rewards"] = '战斗章节的每个难度都必须配置首通奖励。';
                } else {
                    try {
                        self::validateFirstClearRewardsOrFail($firstClearRewards);
                    } catch (ValidationException $exception) {
                        foreach ($exception->errors() as $field => $messages) {
                            $errors["chapters.{$index}.difficulties.{$difficultyIndex}.{$field}"] = $messages[0];
                        }
                    }
                }

                foreach ($monsterEntries as $monsterIndex => $entry) {
                    if (! is_array($entry)) {
                        $errors["chapters.{$index}.difficulties.{$difficultyIndex}.monster_entries.{$monsterIndex}"] = '怪物条目格式错误。';
                        continue;
                    }

                    if (trim((string) ($entry['monster_id'] ?? '')) === '') {
                        $errors["chapters.{$index}.difficulties.{$difficultyIndex}.monster_entries.{$monsterIndex}.monster_id"] = '请填写 monster_id。';
                    }

                    if (! isset($entry['spawn_type']) || ! in_array((string) $entry['spawn_type'], array_keys(\App\Support\MonsterModuleSupport::spawnTypeOptions()), true)) {
                        $errors["chapters.{$index}.difficulties.{$difficultyIndex}.monster_entries.{$monsterIndex}.spawn_type"] = 'spawn_type 非法。';
                    }

                    $minCount = (int) ($entry['min_count'] ?? 0);
                    $maxCount = (int) ($entry['max_count'] ?? 0);
                    if ($minCount < 1) {
                        $errors["chapters.{$index}.difficulties.{$difficultyIndex}.monster_entries.{$monsterIndex}.min_count"] = '最小数量必须大于等于 1。';
                    }
                    if ($maxCount < $minCount) {
                        $errors["chapters.{$index}.difficulties.{$difficultyIndex}.monster_entries.{$monsterIndex}.max_count"] = '最大数量不能小于最小数量。';
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
